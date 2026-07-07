import { useEffect } from 'react';
import { router } from '@inertiajs/react';
import {
  DashboardLayout,
  DataTable,
  Button,
  Badge,
  HStack, VStack, Box,
  Text,
  Card, CardBody,
  KPI,
  Sparkline,
  ProgressRow,
  Avatar,
  EmptyState,
} from '@aero/ui';
import App from '@/Pages/App.jsx';
import BillingRail from './BillingRail.jsx';

/* Invoice status → Badge intent + label (real enum: paid/issued/overdue/draft/void/refunded). */
const INV = {
  paid:     { intent: 'success', label: 'Paid' },
  issued:   { intent: 'warning', label: 'Open' },
  overdue:  { intent: 'danger',  label: 'Overdue' },
  draft:    { intent: 'neutral', label: 'Draft' },
  void:     { intent: 'neutral', label: 'Void' },
  refunded: { intent: 'indigo',  label: 'Refunded' },
};

const money = (v) => `$${Number(v ?? 0).toLocaleString(undefined, { maximumFractionDigits: 0 })}`;
const moneyK = (v) => {
  const n = Number(v ?? 0);
  return n >= 1000 ? `$${(n / 1000).toLocaleString(undefined, { maximumFractionDigits: 1 })}k` : money(n);
};

export default function BillingDashboard({ overview }) {
  const o = overview ?? {};
  const h = o.heroes ?? {};
  const life = o.lifecycle ?? {};
  const inv = o.invoices ?? {};
  const trend = o.revenueTrend ?? [];
  const needs = o.needsAttention ?? [];
  const gateways = o.gateways ?? [];
  const recent = o.recent ?? [];

  // ~30s live refresh of the volatile bits (invoices/needsAttention/recent).
  useEffect(() => {
    const id = setInterval(() => {
      router.reload({ only: ['live'], preserveScroll: true, preserveState: true });
    }, 30000);
    return () => clearInterval(id);
  }, []);

  const trendSpark = trend.map((t) => Number(t.mrr) || 0);
  const deltaUp = (h.mrrDeltaPct ?? 0) >= 0;

  const recentColumns = [
    {
      key: 'tenant', label: 'Tenant',
      render: (r) => (
        <HStack gap={2} align="center">
          <Avatar name={r.tenant || '—'} size={24} />
          <Text size="sm">{r.tenant || '—'}</Text>
        </HStack>
      ),
    },
    { key: 'invoice', label: 'Invoice', render: (r) => <Text size="sm" tone="secondary">{r.invoice}</Text> },
    { key: 'amount', label: 'Amount', align: 'right', render: (r) => <Text size="sm">{money(r.amount)}</Text> },
    {
      key: 'status', label: 'Status',
      render: (r) => {
        const s = INV[r.status] ?? { intent: 'neutral', label: r.status };
        return <Badge intent={s.intent} dot>{s.label}</Badge>;
      },
    },
    { key: 'date', label: 'Date', render: (r) => <Text size="sm" tone="tertiary">{r.date ?? '—'}</Text> },
  ];

  return (
    <DashboardLayout
      title="Billing"
      breadcrumb={[
        { label: 'Platform Admin', href: route('platform.admin.onboarding.dashboard') },
        { label: 'Billing' },
      ]}
      description="Recurring revenue, collections, and payment health at a glance."
      actions={
        <Button intent="primary" onClick={() => router.visit(route('platform.admin.billing.invoices.index'))}>
          View invoices
        </Button>
      }
    >
      <VStack gap={5}>

        {/* Revenue heroes */}
        <HStack gap={4} wrap="wrap">
          <Box grow>
            <KPI label="Monthly recurring revenue" value={moneyK(h.mrr)}
                 delta={`${deltaUp ? '+' : ''}${h.mrrDeltaPct ?? 0}%`} deltaTrend={deltaUp ? 'up' : 'down'}
                 sparkline={trendSpark} />
          </Box>
          <Box grow>
            <KPI label="Annual run-rate" value={moneyK(h.arr)} sparkline={trendSpark} />
          </Box>
          <Box grow>
            <KPI label="Active subscriptions" value={h.activeSubs ?? 0}
                 delta={`${h.trialingSubs ?? 0} trialing`} deltaTrend="up" />
          </Box>
          <Box grow>
            <KPI label="Overdue invoices" value={h.overdueCount ?? 0}
                 delta={`${money(h.overdueAmount)} at risk`} deltaTrend={(h.overdueCount ?? 0) > 0 ? 'down' : 'up'} />
          </Box>
        </HStack>

        {/* Revenue trend + subscription lifecycle */}
        <HStack gap={4} wrap="wrap" align="stretch">
          <Box grow>
            <Card>
              <CardBody>
                <VStack gap={3}>
                  <HStack justify="between" align="center">
                    <VStack gap={0}>
                      <Text weight={600}>Revenue trend</Text>
                      <Text size="xs" tone="tertiary">Recurring revenue · last {trend.length} months</Text>
                    </VStack>
                    <Text size="lg" weight={700}>{moneyK(h.mrr)}</Text>
                  </HStack>
                  {trendSpark.length > 0
                    ? <Sparkline data={trendSpark} height={88} intent="cyan" />
                    : <EmptyState icon="chartBar" title="No revenue history yet" />}
                  <HStack gap={4}>
                    {trend.map((t) => (
                      <VStack key={t.month} gap={0} align="center">
                        <Text size="xs" tone="tertiary">{t.month}</Text>
                        <Text size="xs" weight={600}>{moneyK(t.mrr)}</Text>
                      </VStack>
                    ))}
                  </HStack>
                </VStack>
              </CardBody>
            </Card>
          </Box>
          <Box grow>
            <Card>
              <CardBody>
                <VStack gap={3}>
                  <VStack gap={0}>
                    <Text weight={600}>Subscription lifecycle</Text>
                    <Text size="xs" tone="tertiary">Where accounts sit today</Text>
                  </VStack>
                  <VStack gap={2}>
                    <ProgressRow label="Active"    value={life.active ?? 0}    max={life.total || 1} intent="success" />
                    <ProgressRow label="Trialing"  value={life.trialing ?? 0}  max={life.total || 1} intent="cyan" />
                    <ProgressRow label="Cancelled" value={life.cancelled ?? 0} max={life.total || 1} intent="amber" />
                  </VStack>
                  {life.churnPct != null && (
                    <Text size="xs" tone="tertiary">Churn (30d) · <Text as="span" size="xs" weight={600}>{life.churnPct}%</Text></Text>
                  )}
                </VStack>
              </CardBody>
            </Card>
          </Box>
        </HStack>

        {/* Invoice collections + needs attention */}
        <HStack gap={4} wrap="wrap" align="stretch">
          <Box grow>
            <Card>
              <CardBody>
                <VStack gap={3}>
                  <HStack justify="between" align="center">
                    <VStack gap={0}>
                      <Text weight={600}>Invoice collections</Text>
                      <Text size="xs" tone="tertiary">{inv.total ?? 0} invoices this cycle</Text>
                    </VStack>
                    <Badge intent="success">{inv.collectedPct ?? 0}% collected</Badge>
                  </HStack>
                  <VStack gap={2}>
                    <ProgressRow label={`Paid · ${inv.paid?.count ?? 0}`}    value={inv.paid?.count ?? 0}    max={inv.total || 1} intent="success" />
                    <ProgressRow label={`Open · ${inv.open?.count ?? 0}`}    value={inv.open?.count ?? 0}    max={inv.total || 1} intent="amber" />
                    <ProgressRow label={`Overdue · ${inv.overdue?.count ?? 0}`} value={inv.overdue?.count ?? 0} max={inv.total || 1} intent="cyan" />
                  </VStack>
                </VStack>
              </CardBody>
            </Card>
          </Box>
          <Box grow>
            <Card>
              <CardBody>
                <VStack gap={3}>
                  <HStack justify="between" align="center">
                    <VStack gap={0}>
                      <Text weight={600}>Needs attention</Text>
                      <Text size="xs" tone="tertiary">Overdue · oldest first</Text>
                    </VStack>
                    {(h.overdueCount ?? 0) > 0 && <Badge intent="danger" dot>{h.overdueCount} overdue</Badge>}
                  </HStack>
                  {needs.length === 0
                    ? <EmptyState icon="checkCircle" title="Nothing overdue" description="All invoices are on track." />
                    : (
                      <VStack gap={2}>
                        {needs.map((n) => (
                          <HStack key={n.invoice} gap={3} align="center">
                            <Avatar name={n.tenant} size={28} />
                            <VStack gap={0} grow>
                              <Text size="sm" weight={600}>{n.tenant}</Text>
                              <Text size="xs" tone="tertiary">
                                {n.invoice}{n.daysOverdue != null ? ` · ${n.daysOverdue} days overdue` : ''}
                              </Text>
                            </VStack>
                            <Text size="sm" weight={600}>{money(n.amount)}</Text>
                          </HStack>
                        ))}
                      </VStack>
                    )}
                </VStack>
              </CardBody>
            </Card>
          </Box>
        </HStack>

        {/* Payment gateways */}
        <Card>
          <CardBody>
            <VStack gap={3}>
              <VStack gap={0}>
                <Text weight={600}>Payment gateways</Text>
                <Text size="xs" tone="tertiary">{gateways.length} configured</Text>
              </VStack>
              <HStack gap={3} wrap="wrap">
                {gateways.map((g) => (
                  <Box grow key={g.code}>
                    <HStack gap={3} align="center">
                      <Avatar name={g.label} size={30} />
                      <VStack gap={0} grow>
                        <Text size="sm" weight={600}>{g.label}</Text>
                        <Text size="xs" tone="tertiary">{g.isDefault ? 'Default' : g.code}</Text>
                      </VStack>
                      <Badge intent={g.enabled ? 'success' : 'neutral'} dot>{g.enabled ? 'Live' : 'Off'}</Badge>
                    </HStack>
                  </Box>
                ))}
              </HStack>
            </VStack>
          </CardBody>
        </Card>

        {/* Recent transactions */}
        <Card>
          <CardBody>
            <VStack gap={3}>
              <HStack justify="between" align="center">
                <VStack gap={0}>
                  <Text weight={600}>Recent transactions</Text>
                  <Text size="xs" tone="tertiary">Latest invoices &amp; payments</Text>
                </VStack>
                <Button intent="ghost" size="sm" onClick={() => router.visit(route('platform.admin.billing.invoices.index'))}>
                  All invoices
                </Button>
              </HStack>
              <Box className="aeos-table-wrap">
                <DataTable columns={recentColumns} rows={recent} empty="No transactions yet." />
              </Box>
            </VStack>
          </CardBody>
        </Card>

      </VStack>
    </DashboardLayout>
  );
}

BillingDashboard.layout = (page) => (
  <App title="Billing" railTitle="Billing" rail={<BillingRail />}>{page}</App>
);
