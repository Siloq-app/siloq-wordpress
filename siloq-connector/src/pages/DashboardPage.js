/**
 * Dashboard Page Component
 * Modern React UI using @wordpress/components
 */

import { useState, useEffect } from '@wordpress/element';
import { apiFetch } from '../utils/api';
import {
    Button,
    Card,
    CardHeader,
    CardBody,
    Notice,
    Spinner,
    ProgressBar,
    __experimentalText as Text,
    __experimentalHeading as Heading,
    __experimentalHStack as HStack,
    __experimentalVStack as VStack,
    __experimentalGrid as Grid,
    TabPanel,
    Icon,
    Badge,
} from '@wordpress/components';
import {
    chartBar,
    page,
    cloudUpload,
    plugins,
    warning,
    check,
    external,
} from '@wordpress/icons';

// Stat Card Component
function StatCard({ title, value, icon, description, variant = 'default' }) {
    const getVariantStyles = () => {
        switch (variant) {
            case 'success':
                return { borderLeft: '4px solid #46b450' };
            case 'warning':
                return { borderLeft: '4px solid #ffb900' };
            case 'error':
                return { borderLeft: '4px solid #dc3232' };
            default:
                return { borderLeft: '4px solid #2271b1' };
        }
    };

    return (
        <Card style={getVariantStyles()}>
            <CardBody>
                <HStack spacing={3} alignment="start">
                    <Icon icon={icon} size={32} />
                    <VStack spacing={1}>
                        <Text variant="muted" size="small">{title}</Text>
                        <Heading level={3} style={{ margin: 0 }}>{value}</Heading>
                        {description && (
                            <Text size="small">{description}</Text>
                        )}
                    </VStack>
                </HStack>
            </CardBody>
        </Card>
    );
}

// Score Ring Component
function ScoreRing({ score }) {
    const getScoreColor = (s) => {
        if (s >= 80) return '#46b450';
        if (s >= 60) return '#ffb900';
        return '#dc3232';
    };

    const getScoreLabel = (s) => {
        if (s >= 80) return 'Excellent';
        if (s >= 60) return 'Good';
        if (s >= 40) return 'Needs Work';
        return 'Critical';
    };

    const size = 120;
    const strokeWidth = 8;
    const radius = (size - strokeWidth) / 2;
    const circumference = radius * 2 * Math.PI;
    const offset = circumference - (score / 100) * circumference;

    return (
        <VStack alignment="center" spacing={2}>
            <div style={{ position: 'relative', width: size, height: size }}>
                <svg width={size} height={size} style={{ transform: 'rotate(-90deg)' }}>
                    <circle
                        cx={size / 2}
                        cy={size / 2}
                        r={radius}
                        fill="none"
                        stroke="#e0e0e0"
                        strokeWidth={strokeWidth}
                    />
                    <circle
                        cx={size / 2}
                        cy={size / 2}
                        r={radius}
                        fill="none"
                        stroke={getScoreColor(score)}
                        strokeWidth={strokeWidth}
                        strokeDasharray={circumference}
                        strokeDashoffset={offset}
                        strokeLinecap="round"
                        style={{ transition: 'stroke-dashoffset 0.5s ease' }}
                    />
                </svg>
                <div style={{
                    position: 'absolute',
                    top: '50%',
                    left: '50%',
                    transform: 'translate(-50%, -50%)',
                    textAlign: 'center',
                }}>
                    <Heading level={2} style={{ margin: 0 }}>{score}</Heading>
                </div>
            </div>
            <Badge variant={score >= 60 ? 'success' : score >= 40 ? 'warning' : 'error'}>
                {getScoreLabel(score)}
            </Badge>
        </VStack>
    );
}

// Quick Actions Component
function QuickActions({ hasApiKey, hasAnthropicKey }) {
    const actions = [
        {
            title: 'Page Sync',
            description: 'Sync pages with Siloq',
            icon: cloudUpload,
            link: 'admin.php?page=siloq-sync',
            disabled: !hasApiKey,
        },
        {
            title: 'Content Import',
            description: 'Import blog content',
            icon: plugins,
            link: 'admin.php?page=siloq-content-import',
            disabled: !hasApiKey,
        },
        {
            title: 'AI Generator',
            description: hasAnthropicKey ? 'Generate content with AI' : 'Configure Anthropic API key',
            icon: hasAnthropicKey ? check : warning,
            link: 'admin.php?page=siloq-settings',
            variant: hasAnthropicKey ? 'default' : 'warning',
        },
    ];

    return (
        <Card>
            <CardHeader>
                <Heading level={4}>Quick Actions</Heading>
            </CardHeader>
            <CardBody>
                <VStack spacing={3}>
                    {actions.map((action, index) => (
                        <Card key={index} style={{ marginBottom: '8px' }}>
                            <CardBody>
                                <HStack justify="space-between" alignment="center">
                                    <HStack spacing={3} alignment="center">
                                        <Icon
                                            icon={action.icon}
                                            style={{ color: action.variant === 'warning' ? '#ffb900' : undefined }}
                                        />
                                        <VStack spacing={1}>
                                            <Text weight={600}>{action.title}</Text>
                                            <Text size="small" variant="muted">{action.description}</Text>
                                        </VStack>
                                    </HStack>
                                    <Button
                                        variant="secondary"
                                        size="small"
                                        href={action.link}
                                        disabled={action.disabled}
                                        icon={external}
                                    >
                                        Open
                                    </Button>
                                </HStack>
                            </CardBody>
                        </Card>
                    ))}
                </VStack>
            </CardBody>
        </Card>
    );
}

export default function DashboardPage() {
    const [stats, setStats] = useState(null);
    const [isLoading, setIsLoading] = useState(true);
    const [notice, setNotice] = useState(null);
    const [activeTab, setActiveTab] = useState('overview');

    useEffect(() => {
        loadStats();
    }, []);

    const loadStats = async () => {
        try {
            const response = await apiFetch({
                path: '/wp-json/siloq/v1/dashboard/stats',
                method: 'GET',
            });
            setStats(response);
        } catch (error) {
            setNotice({
                status: 'error',
                message: error.message || 'Failed to load dashboard stats.',
            });
        } finally {
            setIsLoading(false);
        }
    };

    const tabs = [
        { name: 'overview', title: 'Overview' },
        { name: 'pages', title: 'Pages' },
        { name: 'recommendations', title: 'Recommendations' },
    ];

    if (isLoading) {
        return (
            <div className="siloq-dashboard-page">
                <Card>
                    <CardBody>
                        <HStack alignment="center" justify="center" spacing={4}>
                            <Spinner />
                            <Text>Loading dashboard...</Text>
                        </HStack>
                    </CardBody>
                </Card>
            </div>
        );
    }

    if (!stats) {
        return (
            <Notice status="error">
                Failed to load dashboard data. Please try refreshing the page.
            </Notice>
        );
    }

    const syncPercentage = stats.total_pages > 0
        ? Math.round((stats.synced_pages / stats.total_pages) * 100)
        : 0;

    return (
        <div className="siloq-dashboard-page wrap">
            <VStack spacing={4}>
                <HStack justify="space-between" alignment="center">
                    <Heading level={1}>Siloq Dashboard</Heading>
                    <HStack spacing={2}>
                        {stats.site_id && (
                            <Badge>Site ID: {stats.site_id}</Badge>
                        )}
                        <Button
                            variant="secondary"
                            size="small"
                            onClick={loadStats}
                            isBusy={isLoading}
                        >
                            Refresh
                        </Button>
                    </HStack>
                </HStack>

                {notice && (
                    <Notice
                        status={notice.status}
                        onRemove={() => setNotice(null)}
                    >
                        {notice.message}
                    </Notice>
                )}

                {!stats.has_api_key && (
                    <Notice status="warning" isDismissible={false}>
                        <strong>Setup Required:</strong> Please configure your Siloq API key in the{' '}
                        <a href="admin.php?page=siloq-settings">Settings</a> to connect your site.
                    </Notice>
                )}

                <TabPanel
                    tabs={tabs}
                    initialTabName="overview"
                    onSelect={setActiveTab}
                >
                    {(tab) => (
                        <>
                            {tab.name === 'overview' && (
                                <VStack spacing={4}>
                                    <Grid columns={4} gap={4}>
                                        <ScoreRing score={stats.site_score} />

                                        <StatCard
                                            title="Total Pages"
                                            value={stats.total_pages}
                                            icon={page}
                                            description="Published pages on your site"
                                        />

                                        <StatCard
                                            title="Synced Pages"
                                            value={stats.synced_pages}
                                            icon={cloudUpload}
                                            description={`${syncPercentage}% of total pages`}
                                            variant={syncPercentage >= 80 ? 'success' : syncPercentage >= 50 ? 'warning' : 'error'}
                                        />

                                        <StatCard
                                            title="Pending"
                                            value={stats.total_pages - stats.synced_pages}
                                            icon={chartBar}
                                            description="Pages awaiting sync"
                                            variant={stats.total_pages - stats.synced_pages === 0 ? 'success' : 'warning'}
                                        />
                                    </Grid>

                                    <Grid columns={2} gap={4}>
                                        <QuickActions
                                            hasApiKey={stats.has_api_key}
                                            hasAnthropicKey={stats.has_anthropic_key}
                                        />

                                        <Card>
                                            <CardHeader>
                                                <Heading level={4}>Sync Status</Heading>
                                            </CardHeader>
                                            <CardBody>
                                                <VStack spacing={3}>
                                                    <ProgressBar
                                                        value={syncPercentage}
                                                        color={syncPercentage >= 80 ? '#46b450' : syncPercentage >= 50 ? '#ffb900' : '#dc3232'}
                                                    />
                                                    <HStack justify="space-between">
                                                        <Text size="small">
                                                            {stats.synced_pages} of {stats.total_pages} pages synced
                                                        </Text>
                                                        <Text size="small" weight={600}>
                                                            {syncPercentage}%
                                                        </Text>
                                                    </HStack>

                                                    {stats.total_pages - stats.synced_pages > 0 && (
                                                        <Button
                                                            variant="primary"
                                                            href="admin.php?page=siloq-sync"
                                                            style={{ width: '100%' }}
                                                        >
                                                            Sync Remaining Pages
                                                        </Button>
                                                    )}
                                                </VStack>
                                            </CardBody>
                                        </Card>
                                    </Grid>
                                </VStack>
                            )}

                            {tab.name === 'pages' && (
                                <Card>
                                    <CardHeader>
                                        <Heading level={4}>Page Management</Heading>
                                    </CardHeader>
                                    <CardBody>
                                        <Text>
                                            Page management features will be available here.
                                            Use the <a href="admin.php?page=siloq-sync">Page Sync</a> tool to manage your pages.
                                        </Text>
                                    </CardBody>
                                </Card>
                            )}

                            {tab.name === 'recommendations' && (
                                <Card>
                                    <CardHeader>
                                        <Heading level={4}>Recommendations</Heading>
                                    </CardHeader>
                                    <CardBody>
                                        {stats.has_api_key ? (
                                            <Text>
                                                View and manage your Siloq recommendations.
                                                Check the <a href="admin.php?page=siloq-approvals">Approvals</a> page for pending recommendations.
                                            </Text>
                                        ) : (
                                            <Notice status="warning" isDismissible={false}>
                                                Connect your Siloq API key to see recommendations.
                                            </Notice>
                                        )}
                                    </CardBody>
                                </Card>
                            )}
                        </>
                    )}
                </TabPanel>
            </VStack>
        </div>
    );
}
