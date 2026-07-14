<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Notifications Package — Notification Infrastructure
    |--------------------------------------------------------------------------
    | Scope: BOTH platform + tenant (infrastructure layer, not a UI module)
    |
    | aero-notifications is NOT a user-facing module. It is the shared notification
    | infrastructure used by all modules for email, SMS, push, in-app, and webhook
    | notifications. It does not appear in the module marketplace.
    |
    | Tenancy concern:
    |   - Email templates are tenant-scoped (custom branding)
    *   - Notification preferences are tenant-scoped
    *   - Queue workers process notifications in tenant context
    *   - Platform notifications (landlord) use central DB
    *   - Tenant notifications use tenant DB
    */

    'code' => 'notifications',
    'schema_version' => '2.0',
    'scope' => 'infrastructure',
    'name' => 'Notification Infrastructure',
    'description' => 'Shared notification layer: email, SMS, push, in-app, webhooks, templates, preferences, and queue processing for both tenant and platform contexts.',
    'icon' => 'BellIcon',
    'route_prefix' => null,
    'category' => 'infrastructure',
    'priority' => 0,
    'is_core' => true,
    'is_active' => true,
    'enabled' => true,
    'version' => '1.0.0',
    'min_plan' => null,
    'license_type' => 'platform',
    'dependencies' => [],
    'release_date' => '2024-01-01',
    'marketplace_visible' => false,

    'tenancy' => [
        'tenant_aware' => true,
        'uses_tenant_db' => true,
        'central_tables' => [
            'platform_notification_templates',
            'platform_notification_logs',
        ],
        'tenant_tables' => [
            'notification_templates',
            'notification_preferences',
            'notification_logs',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Submodules
    |--------------------------------------------------------------------------
    |
    | Every component below is a REAL tab of the notifications command centre
    | (/notifications?tab=…), backed by NotificationCenterController. A component
    | declared here becomes a nav entry and an HRMAC permission set, so declaring
    | one that has no controller behind it produces a dead link in the sidebar.
    |
    | This is why `sms_engine` and `push_engine` are NOT declared: the package
    | ships SmsService/SmsGatewayService/FcmNotificationService and their channel
    | adapters (used by the pipeline), but there is no gateway-admin, SMS-log or
    | push-topic page behind them. Advertising those pages produced 5 phantom nav
    | links. SMS and push are configured — and test-fired — through the `settings`
    | submodule's Channels tab, which is real. Declare them again only when the
    | pages exist.
    |
    | Also resolves aero-core Task 12: email_engine belongs HERE, next to the
    | controllers/jobs/services that implement it. Operators upgrading must re-run
    | `php artisan modules:sync` and re-grant roles that used core.email_engine.*
    | → notifications.email_engine.*.
    */
    'submodules' => [
        [
            'code' => 'in_app',
            'name' => 'Inbox',
            'icon' => 'InboxIcon',
            'description' => 'The user-facing notification inbox.',
            'route' => '/notifications?tab=inbox',
            'components' => [
                [
                    'code' => 'inbox', 'name' => 'Inbox', 'type' => 'page', 'route' => '/notifications?tab=inbox',
                    'actions' => [
                        ['code' => 'view', 'name' => 'View Inbox'],
                        ['code' => 'mark_read', 'name' => 'Mark Read'],
                        ['code' => 'delete', 'name' => 'Delete Notification'],
                    ],
                ],
            ],
        ],
        [
            'code' => 'email_engine',
            'name' => 'Email Engine',
            'icon' => 'EnvelopeIcon',
            'description' => 'Delivery log, bounces, suppression list, deliverability and templates.',
            'route' => '/notifications?tab=log',
            'components' => [
                [
                    'code' => 'logs', 'name' => 'Delivery Log', 'type' => 'page', 'route' => '/notifications?tab=log',
                    'actions' => [
                        ['code' => 'view', 'name' => 'View Delivery Log'],
                        ['code' => 'resend', 'name' => 'Resend Notification'],
                        ['code' => 'export', 'name' => 'Export Delivery Log'],
                    ],
                ],
                [
                    'code' => 'bounces', 'name' => 'Bounces & Complaints', 'type' => 'page', 'route' => '/notifications?tab=bounces',
                    'actions' => [
                        ['code' => 'view', 'name' => 'View Bounces'],
                        ['code' => 'suppress', 'name' => 'Suppress Bounced Address'],
                    ],
                ],
                [
                    'code' => 'suppression_list', 'name' => 'Suppression List', 'type' => 'page', 'route' => '/notifications?tab=suppression',
                    'actions' => [
                        ['code' => 'view', 'name' => 'View Suppression'],
                        ['code' => 'add', 'name' => 'Add Address'],
                        ['code' => 'remove', 'name' => 'Remove Address'],
                        ['code' => 'export', 'name' => 'Export Suppression List'],
                    ],
                ],
                [
                    'code' => 'deliverability', 'name' => 'Deliverability', 'type' => 'page', 'route' => '/notifications?tab=deliverability',
                    'actions' => [
                        ['code' => 'view', 'name' => 'View Deliverability Score'],
                        ['code' => 'test_smtp', 'name' => 'Re-check DNS / Test SMTP'],
                    ],
                ],
                [
                    'code' => 'templates', 'name' => 'Templates', 'type' => 'page', 'route' => '/notifications?tab=templates',
                    'actions' => [
                        ['code' => 'view', 'name' => 'View Templates'],
                        ['code' => 'create', 'name' => 'Create Template'],
                        ['code' => 'update', 'name' => 'Update Template'],
                        ['code' => 'delete', 'name' => 'Delete Template'],
                        ['code' => 'duplicate', 'name' => 'Duplicate Template'],
                    ],
                ],
            ],
        ],
        [
            'code' => 'settings',
            'name' => 'Channels',
            'icon' => 'Cog8ToothIcon',
            'description' => 'Enable and configure the email, SMS, push and in-app channels.',
            'route' => '/notifications?tab=channels',
            'components' => [
                [
                    'code' => 'channels', 'name' => 'Channels', 'type' => 'page', 'route' => '/notifications?tab=channels',
                    'actions' => [
                        ['code' => 'view', 'name' => 'View Channels'],
                        ['code' => 'configure', 'name' => 'Configure Channel'],
                        ['code' => 'test', 'name' => 'Send Test Notification'],
                    ],
                ],
            ],
        ],
        [
            'code' => 'preferences',
            'name' => 'My Notification Preferences',
            'icon' => 'Cog6ToothIcon',
            'description' => 'Per-user channel, digest and quiet-hours preferences.',
            'route' => '/notifications?tab=preferences',
            'components' => [
                [
                    'code' => 'channels', 'name' => 'Channel Preferences', 'type' => 'page', 'route' => '/notifications?tab=preferences',
                    'actions' => [
                        ['code' => 'view', 'name' => 'View Preferences'],
                        ['code' => 'update', 'name' => 'Update Preferences'],
                    ],
                ],
            ],
        ],
    ],
];
