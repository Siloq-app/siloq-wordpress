/**
 * Sync Page Component
 * Modern React UI using @wordpress/components
 */

import { useState, useEffect } from '@wordpress/element';
import { apiFetch } from '../utils/api';
import {
    Button,
    Card,
    CardHeader,
    CardBody,
    CardFooter,
    Notice,
    Spinner,
    ProgressBar,
    SelectControl,
    ToggleControl,
    CheckboxControl,
    Modal,
    __experimentalText as Text,
    __experimentalHeading as Heading,
    __experimentalSubheading as Subheading,
    __experimentalHStack as HStack,
    __experimentalVStack as VStack,
    __experimentalGrid as Grid,
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableCell,
} from '@wordpress/components';
import {
    cloudUpload,
    update,
    check,
    warning,
    info,
    trash,
    plugins,
} from '@wordpress/icons';

export default function SyncPage() {
    const [syncStatus, setSyncStatus] = useState(null);
    const [isLoading, setIsLoading] = useState(true);
    const [isSyncing, setIsSyncing] = useState(false);
    const [syncMode, setSyncMode] = useState('all');
    const [notice, setNotice] = useState(null);
    const [showConfirmModal, setShowConfirmModal] = useState(false);
    const [syncProgress, setSyncProgress] = useState({
        current: 0,
        total: 0,
        message: '',
    });

    useEffect(() => {
        loadSyncStatus();
    }, []);

    const loadSyncStatus = async () => {
        try {
            const response = await apiFetch({
                path: '/wp-json/siloq/v1/sync/status',
                method: 'GET',
            });
            setSyncStatus(response);
        } catch (error) {
            setNotice({
                status: 'error',
                message: error.message || 'Failed to load sync status.',
            });
        } finally {
            setIsLoading(false);
        }
    };

    const startSync = async () => {
        setIsSyncing(true);
        setShowConfirmModal(false);
        setNotice({
            status: 'info',
            message: 'Sync started... This may take a few minutes for large sites.',
        });

        try {
            const response = await apiFetch({
                path: '/wp-json/siloq/v1/sync/start',
                method: 'POST',
                data: { mode: syncMode },
            });

            setNotice({
                status: 'success',
                message: response.message || 'Sync completed successfully!',
            });

            // Reload status after sync
            await loadSyncStatus();
        } catch (error) {
            setNotice({
                status: 'error',
                message: error.message || 'Sync failed. Please try again.',
            });
        } finally {
            setIsSyncing(false);
        }
    };

    const confirmSync = () => {
        setShowConfirmModal(true);
    };

    if (isLoading) {
        return (
            <div className="siloq-sync-page">
                <Card>
                    <CardBody>
                        <HStack alignment="center" justify="center" spacing={4}>
                            <Spinner />
                            <Text>Loading sync status...</Text>
                        </HStack>
                    </CardBody>
                </Card>
            </div>
        );
    }

    if (!syncStatus) {
        return (
            <Notice status="error">
                Failed to load sync status. Please try refreshing the page.
            </Notice>
        );
    }

    if (!syncStatus.connected) {
        return (
            <div className="siloq-sync-page wrap">
                <VStack spacing={4}>
                    <Heading level={1}>Page Sync</Heading>

                    <Card>
                        <CardBody>
                            <VStack spacing={3} alignment="center">
                                <Notice status="warning" isDismissible={false}>
                                    <strong>Not Connected:</strong> Your site is not connected to Siloq.
                                    Please configure your API key in the{' '}
                                    <a href="admin.php?page=siloq-settings">Settings</a> page.
                                </Notice>

                                <Button
                                    variant="primary"
                                    href="admin.php?page=siloq-settings"
                                >
                                    Go to Settings
                                </Button>
                            </VStack>
                        </CardBody>
                    </Card>
                </VStack>
            </div>
        );
    }

    const syncPercentage = syncStatus.sync_percentage || 0;
    const pendingCount = syncStatus.pending_pages || 0;

    return (
        <div className="siloq-sync-page wrap">
            <VStack spacing={4}>
                <HStack justify="space-between" alignment="center">
                    <Heading level={1}>Page Sync</Heading>
                    <HStack spacing={2}>
                        <Text variant="muted">Site ID: {syncStatus.site_id}</Text>
                        <Button
                            variant="secondary"
                            size="small"
                            onClick={loadSyncStatus}
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

                <Grid columns={3} gap={4}>
                    <Card>
                        <CardBody>
                            <VStack spacing={2} alignment="center">
                                <Text variant="muted" size="small">Total Pages</Text>
                                <Heading level={2} style={{ margin: 0 }}>{syncStatus.total_pages}</Heading>
                            </VStack>
                        </CardBody>
                    </Card>

                    <Card style={{ borderLeft: '4px solid #46b450' }}>
                        <CardBody>
                            <VStack spacing={2} alignment="center">
                                <Text variant="muted" size="small">Synced</Text>
                                <Heading level={2} style={{ margin: 0, color: '#46b450' }}>{syncStatus.synced_pages}</Heading>
                            </VStack>
                        </CardBody>
                    </Card>

                    <Card style={{ borderLeft: pendingCount > 0 ? '4px solid #ffb900' : '4px solid #46b450' }}>
                        <CardBody>
                            <VStack spacing={2} alignment="center">
                                <Text variant="muted" size="small">Pending</Text>
                                <Heading
                                    level={2}
                                    style={{ margin: 0, color: pendingCount > 0 ? '#ffb900' : '#46b450' }}
                                >
                                    {pendingCount}
                                </Heading>
                            </VStack>
                        </CardBody>
                    </Card>
                </Grid>

                <Card>
                    <CardHeader>
                        <Heading level={4}>Sync Progress</Heading>
                    </CardHeader>
                    <CardBody>
                        <VStack spacing={4}>
                            <ProgressBar
                                value={syncPercentage}
                                color={syncPercentage >= 80 ? '#46b450' : syncPercentage >= 50 ? '#ffb900' : '#dc3232'}
                            />

                            <HStack justify="space-between">
                                <Text>
                                    {syncStatus.synced_pages} of {syncStatus.total_pages} pages synced
                                </Text>
                                <Text weight={600}>{syncPercentage}%</Text>
                            </HStack>

                            {isSyncing && (
                                <HStack alignment="center" spacing={2}>
                                    <Spinner />
                                    <Text>Syncing pages... Please wait.</Text>
                                </HStack>
                            )}
                        </VStack>
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader>
                        <Heading level={4}>Sync Options</Heading>
                    </CardHeader>
                    <CardBody>
                        <VStack spacing={4}>
                            <SelectControl
                                label="Sync Mode"
                                help="Choose which pages to sync"
                                value={syncMode}
                                options={[
                                    { label: 'All Pages', value: 'all' },
                                    { label: 'Only Missing Pages', value: 'missing' },
                                    { label: 'Force Re-sync All', value: 'force' },
                                ]}
                                onChange={(value) => setSyncMode(value)}
                                __nextHasNoMarginBottom
                            />

                            <HStack spacing={2}>
                                <Button
                                    variant="primary"
                                    icon={cloudUpload}
                                    onClick={confirmSync}
                                    isBusy={isSyncing}
                                    disabled={isSyncing}
                                >
                                    {isSyncing ? 'Syncing...' : 'Start Sync'}
                                </Button>

                                {pendingCount > 0 && syncMode === 'all' && (
                                    <Button
                                        variant="secondary"
                                        onClick={() => {
                                            setSyncMode('missing');
                                            confirmSync();
                                        }}
                                        disabled={isSyncing}
                                    >
                                        Sync Only Missing ({pendingCount})
                                    </Button>
                                )}
                            </HStack>

                            <Text size="small" variant="muted">
                                <strong>Note:</strong> Syncing sends your page data to the Siloq platform for analysis.
                                This process may take a few minutes depending on the number of pages.
                            </Text>
                        </VStack>
                    </CardBody>
                </Card>

                {showConfirmModal && (
                    <Modal
                        title="Confirm Sync"
                        onRequestClose={() => setShowConfirmModal(false)}
                        style={{ maxWidth: '500px' }}
                    >
                        <VStack spacing={4}>
                            <Text>
                                You are about to start a <strong>{syncMode === 'all' ? 'full' : syncMode}</strong> sync.
                            </Text>

                            {syncMode === 'all' && (
                                <Notice status="warning" isDismissible={false}>
                                    This will sync all {syncStatus.total_pages} pages. This may take several minutes.
                                </Notice>
                            )}

                            {syncMode === 'force' && (
                                <Notice status="error" isDismissible={false}>
                                    Force re-sync will re-send all pages even if already synced. Use with caution.
                                </Notice>
                            )}

                            <HStack justify="flex-end" spacing={2}>
                                <Button
                                    variant="secondary"
                                    onClick={() => setShowConfirmModal(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    variant="primary"
                                    onClick={startSync}
                                    icon={cloudUpload}
                                >
                                    Start Sync
                                </Button>
                            </HStack>
                        </VStack>
                    </Modal>
                )}
            </VStack>
        </div>
    );
}
