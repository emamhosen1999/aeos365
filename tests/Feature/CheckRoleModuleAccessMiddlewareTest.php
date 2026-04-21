<?php

namespace Tests\Feature;

use Aero\HRMAC\Contracts\RoleModuleAccessInterface;
use Aero\HRMAC\Http\Middleware\CheckRoleModuleAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for CheckRoleModuleAccess middleware.
 *
 * Tests the middleware's path parsing, access checking, and response behavior
 * by invoking it directly (bypassing the full HTTP pipeline which requires
 * a live database for platform middleware).
 */
class CheckRoleModuleAccessMiddlewareTest extends TestCase
{
    protected RoleModuleAccessInterface|Mockery\MockInterface $mockService;

    protected CheckRoleModuleAccess $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockService = Mockery::mock(RoleModuleAccessInterface::class);
        $this->middleware = new CheckRoleModuleAccess($this->mockService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function createMockUser(bool $isSuperAdmin = false, array $roleNames = ['Employee']): object
    {
        $roles = collect($roleNames)->map(fn ($name) => (object) ['name' => $name]);

        $user = new class($isSuperAdmin, $roles)
        {
            public int $id = 1;

            public $roles;

            private bool $isSuperAdmin;

            public function __construct(bool $isSuperAdmin, $roles)
            {
                $this->isSuperAdmin = $isSuperAdmin;
                $this->roles = $roles;
            }

            public function hasAnyRole(array $checkRoles): bool
            {
                return $this->isSuperAdmin;
            }

            public function hasRole(string $role): bool
            {
                return $this->isSuperAdmin;
            }
        };

        return $user;
    }

    protected function createRequest(?object $user = null, bool $expectsJson = false, bool $isInertia = false): Request
    {
        $request = Request::create('/test', 'GET');

        if ($user) {
            $request->setUserResolver(fn () => $user);
        }

        if ($expectsJson) {
            $request->headers->set('Accept', 'application/json');
        }

        if ($isInertia) {
            $request->headers->set('X-Inertia', 'true');
        }

        return $request;
    }

    protected function passThrough(): \Closure
    {
        return fn ($request) => new Response('ok', 200);
    }

    // ─── Authentication Tests ────────────────────────────────────────

    public function test_unauthenticated_json_request_returns_401(): void
    {
        $request = $this->createRequest(user: null, expectsJson: true);

        $response = $this->middleware->handle($request, $this->passThrough(), 'hrm');

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Authentication required.', $data['message']);
    }

    public function test_unauthenticated_non_json_request_gets_redirected(): void
    {
        $request = $this->createRequest(user: null);
        // Mock the host so getLoginUrl uses the admin domain path (avoids tenant route param)
        $request->headers->set('HOST', 'admin.aeos365.test');

        $response = $this->middleware->handle($request, $this->passThrough(), 'hrm');

        $this->assertTrue(
            in_array($response->getStatusCode(), [301, 302]),
            "Expected redirect, got {$response->getStatusCode()}"
        );
    }

    // ─── Super Admin Bypass Tests ────────────────────────────────────

    public function test_super_admin_bypasses_all_checks(): void
    {
        $user = $this->createMockUser(isSuperAdmin: true);
        $request = $this->createRequest(user: $user);

        $this->mockService->shouldNotReceive('userCanAccessModule');
        $this->mockService->shouldNotReceive('userCanAccessSubModule');
        $this->mockService->shouldNotReceive('userCanAccessAction');

        $response = $this->middleware->handle($request, $this->passThrough(), 'hrm.employees.departments');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('ok', $response->getContent());
    }

    // ─── Module-Level Access Tests ───────────────────────────────────

    public function test_module_level_access_granted(): void
    {
        $user = $this->createMockUser();
        $request = $this->createRequest(user: $user);

        $this->mockService->shouldReceive('userCanAccessModule')
            ->with($user, 'hrm')
            ->once()
            ->andReturn(true);

        $response = $this->middleware->handle($request, $this->passThrough(), 'hrm');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_module_level_access_denied(): void
    {
        $user = $this->createMockUser();
        $request = $this->createRequest(user: $user, expectsJson: true);

        $this->mockService->shouldReceive('userCanAccessModule')
            ->with($user, 'hrm')
            ->once()
            ->andReturn(false);

        $response = $this->middleware->handle($request, $this->passThrough(), 'hrm');

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('module_access_denied', $data['reason']);
    }

    // ─── Sub-Module Level Tests ──────────────────────────────────────

    public function test_submodule_level_access_check(): void
    {
        $user = $this->createMockUser();
        $request = $this->createRequest(user: $user);

        $this->mockService->shouldReceive('userCanAccessSubModule')
            ->with($user, 'hrm', 'employees')
            ->once()
            ->andReturn(true);

        $response = $this->middleware->handle($request, $this->passThrough(), 'hrm.employees');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_submodule_access_denied_json(): void
    {
        $user = $this->createMockUser();
        $request = $this->createRequest(user: $user, expectsJson: true);

        $this->mockService->shouldReceive('userCanAccessSubModule')
            ->with($user, 'hrm', 'employees')
            ->once()
            ->andReturn(false);

        $response = $this->middleware->handle($request, $this->passThrough(), 'hrm.employees');

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('module_access_denied', $data['reason']);
        $this->assertStringContainsString('employees', $data['message']);
    }

    // ─── Component-Level Tests ───────────────────────────────────────

    public function test_component_level_falls_back_to_submodule_check(): void
    {
        $user = $this->createMockUser();
        $request = $this->createRequest(user: $user);

        // Three-segment path: hrm.employees.departments → checks sub-module 'employees'
        $this->mockService->shouldReceive('userCanAccessSubModule')
            ->with($user, 'hrm', 'employees')
            ->once()
            ->andReturn(true);

        $response = $this->middleware->handle($request, $this->passThrough(), 'hrm.employees.departments');

        $this->assertEquals(200, $response->getStatusCode());
    }

    // ─── Action-Level Tests ──────────────────────────────────────────

    public function test_action_level_access_check(): void
    {
        $user = $this->createMockUser();
        $request = $this->createRequest(user: $user);

        // Path: hrm.attendance.own-attendance + extra1: punch
        $this->mockService->shouldReceive('userCanAccessAction')
            ->with($user, 'hrm', 'attendance', 'punch')
            ->once()
            ->andReturn(true);

        $response = $this->middleware->handle($request, $this->passThrough(), 'hrm.attendance.own-attendance', 'punch');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_action_level_access_denied(): void
    {
        $user = $this->createMockUser();
        $request = $this->createRequest(user: $user, expectsJson: true);

        $this->mockService->shouldReceive('userCanAccessAction')
            ->with($user, 'hrm', 'attendance', 'punch')
            ->once()
            ->andReturn(false);

        $response = $this->middleware->handle($request, $this->passThrough(), 'hrm.attendance.own-attendance', 'punch');

        $this->assertEquals(403, $response->getStatusCode());
    }

    // ─── Alias Resolution Tests ──────────────────────────────────────

    public function test_alias_time_off_resolves_to_leaves(): void
    {
        $user = $this->createMockUser();
        $request = $this->createRequest(user: $user);

        $this->mockService->shouldReceive('userCanAccessSubModule')
            ->with($user, 'hrm', 'leaves')
            ->once()
            ->andReturn(true);

        $response = $this->middleware->handle($request, $this->passThrough(), 'hrm.time-off');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_alias_hr_reports_resolves_to_hr_analytics(): void
    {
        $user = $this->createMockUser();
        $request = $this->createRequest(user: $user);

        $this->mockService->shouldReceive('userCanAccessSubModule')
            ->with($user, 'hrm', 'hr-analytics')
            ->once()
            ->andReturn(true);

        $response = $this->middleware->handle($request, $this->passThrough(), 'hrm.hr-reports');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_alias_with_component_path(): void
    {
        $user = $this->createMockUser();
        $request = $this->createRequest(user: $user);

        // hrm.time-off.holidays → submodule resolves to 'leaves'
        $this->mockService->shouldReceive('userCanAccessSubModule')
            ->with($user, 'hrm', 'leaves')
            ->once()
            ->andReturn(true);

        $response = $this->middleware->handle($request, $this->passThrough(), 'hrm.time-off.holidays');

        $this->assertEquals(200, $response->getStatusCode());
    }

    // ─── Normalization Tests ─────────────────────────────────────────

    public function test_underscore_normalized_to_dash(): void
    {
        $user = $this->createMockUser();
        $request = $this->createRequest(user: $user);

        $this->mockService->shouldReceive('userCanAccessSubModule')
            ->with($user, 'hrm', 'succession-planning')
            ->once()
            ->andReturn(true);

        $response = $this->middleware->handle($request, $this->passThrough(), 'hrm.succession_planning');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_uppercase_normalized_to_lowercase(): void
    {
        $user = $this->createMockUser();
        $request = $this->createRequest(user: $user);

        $this->mockService->shouldReceive('userCanAccessSubModule')
            ->with($user, 'hrm', 'employees')
            ->once()
            ->andReturn(true);

        $response = $this->middleware->handle($request, $this->passThrough(), 'HRM.Employees');

        $this->assertEquals(200, $response->getStatusCode());
    }

    // ─── Legacy Format Tests ─────────────────────────────────────────

    public function test_legacy_comma_format_module_and_submodule(): void
    {
        $user = $this->createMockUser();
        $request = $this->createRequest(user: $user);

        // Legacy: role.access:hrm,employees → module=hrm, submodule=employees
        $this->mockService->shouldReceive('userCanAccessSubModule')
            ->with($user, 'hrm', 'employees')
            ->once()
            ->andReturn(true);

        $response = $this->middleware->handle($request, $this->passThrough(), 'hrm', 'employees');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_legacy_format_module_only(): void
    {
        $user = $this->createMockUser();
        $request = $this->createRequest(user: $user);

        $this->mockService->shouldReceive('userCanAccessModule')
            ->with($user, 'hrm')
            ->once()
            ->andReturn(true);

        $response = $this->middleware->handle($request, $this->passThrough(), 'hrm', null);

        $this->assertEquals(200, $response->getStatusCode());
    }

    // ─── Inertia Response Tests ──────────────────────────────────────

    public function test_denied_inertia_request_returns_403_with_error_component(): void
    {
        $user = $this->createMockUser();
        $request = $this->createRequest(user: $user, isInertia: true);

        $this->mockService->shouldReceive('userCanAccessSubModule')
            ->with($user, 'hrm', 'employees')
            ->once()
            ->andReturn(false);

        $response = $this->middleware->handle($request, $this->passThrough(), 'hrm.employees');

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('true', $response->headers->get('X-Inertia'));

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Errors/UnifiedError', $data['component']);
        $this->assertEquals(403, $data['props']['error']['code']);
        $this->assertEquals('AccessDenied', $data['props']['error']['type']);
    }
}
