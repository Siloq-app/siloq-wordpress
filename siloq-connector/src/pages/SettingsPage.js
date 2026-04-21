/**
 * Settings Page Component
 * Modern React UI using @wordpress/components
 */

import { useState, useEffect } from '@wordpress/element';
import { apiFetch } from '../utils/api';
import {
    Button,
    Panel,
    PanelBody,
    PanelRow,
    TextControl,
    ToggleControl,
    SelectControl,
    Notice,
    Spinner,
    Card,
    CardHeader,
    CardBody,
    __experimentalText as Text,
    __experimentalHeading as Heading,
    __experimentalHStack as HStack,
    __experimentalVStack as VStack,
} from '@wordpress/components';
import { check, warning, info } from '@wordpress/icons';

export default function SettingsPage() {
    const [settings, setSettings] = useState({
        api_key: '',
        api_url: 'https://api.siloq.ai/api/v1',
        api_timeout: 30,
        webhook_secret: '',
        anthropic_api_key: '',
        anthropic_model: 'claude-3-5-sonnet-20241022',
        cache_duration: 60,
        auto_sync: true,
        sync_on_save: true,
    });
    const [originalSettings, setOriginalSettings] = useState({});
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [isTesting, setIsTesting] = useState(false);
    const [notice, setNotice] = useState(null);
    const [hasChanges, setHasChanges] = useState(false);

    // Load settings on mount
    useEffect(() => {
        loadSettings();
    }, []);

    // Check for changes
    useEffect(() => {
        const changed = JSON.stringify(settings) !== JSON.stringify(originalSettings);
        setHasChanges(changed);
    }, [settings, originalSettings]);

    const loadSettings = async () => {
        try {
            const response = await apiFetch({
                path: '/wp-json/siloq/v1/settings',
                method: 'GET',
            });
            setSettings(response);
            setOriginalSettings(response);
        } catch (error) {
            setNotice({
                status: 'error',
                message: error.message || 'Failed to load settings.',
            });
        } finally {
            setIsLoading(false);
        }
    };

    const saveSettings = async () => {
        setIsSaving(true);
        try {
            await apiFetch({
                path: '/wp-json/siloq/v1/settings',
                method: 'POST',
                data: settings,
            });
            setOriginalSettings(settings);
            setNotice({
                status: 'success',
                message: 'Settings saved successfully!',
            });
        } catch (error) {
            setNotice({
                status: 'error',
                message: error.message || 'Failed to save settings.',
            });
        } finally {
            setIsSaving(false);
        }
    };

    const testConnection = async () => {
        setIsTesting(true);
        try {
            const response = await apiFetch({
                path: '/wp-json/siloq/v1/settings/test-connection',
                method: 'POST',
            });
            setNotice({
                status: 'success',
                message: `${response.message} (API Version: ${response.api_version})`,
            });
        } catch (error) {
            setNotice({
                status: 'error',
                message: error.message || 'Connection test failed.',
            });
        } finally {
            setIsTesting(false);
        }
    };

    const updateSetting = (key, value) => {
        setSettings(prev => ({ ...prev, [key]: value }));
    };

    if (isLoading) {
        return (
            <div className="siloq-settings-page">
                <Card>
                    <CardBody>
                        <HStack alignment="center" justify="center" spacing={4}>
                            <Spinner />
                            <Text>Loading settings...</Text>
                        </HStack>
                    </CardBody>
                </Card>
            </div>
        );
    }

    return (
        <div className="siloq-settings-page wrap">
            <VStack spacing={4}>
                <Heading level={1}>Siloq Settings</Heading>

                {notice && (
                    <Notice
                        status={notice.status}
                        onRemove={() => setNotice(null)}
                        actions={[
                            {
                                label: 'Dismiss',
                                onClick: () => setNotice(null),
                            },
                        ]}
                    >
                        {notice.message}
                    </Notice>
                )}

                <Panel>
                    <PanelBody title="API Configuration" initialOpen={true} icon={info}>
                        <PanelRow>
                            <TextControl
                                label="Siloq API Key"
                                help="Your Siloq API key (starts with sk_siloq_)"
                                value={settings.api_key}
                                onChange={(value) => updateSetting('api_key', value)}
                                placeholder="sk_siloq_..."
                                type="password"
                                __nextHasNoMarginBottom
                            />
                        </PanelRow>

                        <PanelRow>
                            <TextControl
                                label="API URL"
                                help="The Siloq API endpoint URL"
                                value={settings.api_url}
                                onChange={(value) => updateSetting('api_url', value)}
                                placeholder="https://api.siloq.ai/api/v1"
                                __nextHasNoMarginBottom
                            />
                        </PanelRow>

                        <PanelRow>
                            <HStack spacing={4} alignment="start">
                                <TextControl
                                    label="API Timeout"
                                    help="Request timeout in seconds (5-120)"
                                    value={settings.api_timeout}
                                    onChange={(value) => updateSetting('api_timeout', parseInt(value) || 30)}
                                    type="number"
                                    min={5}
                                    max={120}
                                    style={{ width: '120px' }}
                                    __nextHasNoMarginBottom
                                />
                                <TextControl
                                    label="Cache Duration"
                                    help="Cache duration in minutes (0-1440)"
                                    value={settings.cache_duration}
                                    onChange={(value) => updateSetting('cache_duration', parseInt(value) || 60)}
                                    type="number"
                                    min={0}
                                    max={1440}
                                    style={{ width: '120px' }}
                                    __nextHasNoMarginBottom
                                />
                            </HStack>
                        </PanelRow>

                        <PanelRow>
                            <HStack spacing={2} justify="flex-start">
                                <Button
                                    variant="secondary"
                                    onClick={testConnection}
                                    isBusy={isTesting}
                                    disabled={isTesting || !settings.api_key}
                                    icon={isTesting ? undefined : check}
                                >
                                    {isTesting ? 'Testing...' : 'Test Connection'}
                                </Button>
                            </HStack>
                        </PanelRow>
                    </PanelBody>
                </Panel>

                <Panel>
                    <PanelBody title="AI Configuration" initialOpen={false}>
                        <PanelRow>
                            <TextControl
                                label="Anthropic API Key"
                                help="Your Anthropic API key for AI content generation (optional)"
                                value={settings.anthropic_api_key}
                                onChange={(value) => updateSetting('anthropic_api_key', value)}
                                placeholder="sk-ant-..."
                                type="password"
                                __nextHasNoMarginBottom
                            />
                        </PanelRow>

                        <PanelRow>
                            <SelectControl
                                label="AI Model"
                                help="Select the Claude model to use"
                                value={settings.anthropic_model}
                                options={[
                                    { label: 'Claude 3.5 Sonnet', value: 'claude-3-5-sonnet-20241022' },
                                    { label: 'Claude 3.5 Haiku', value: 'claude-3-5-haiku-20241022' },
                                    { label: 'Claude 3 Opus', value: 'claude-3-opus-20240229' },
                                    { label: 'Claude 3 Sonnet', value: 'claude-3-sonnet-20240229' },
                                    { label: 'Claude 3 Haiku', value: 'claude-3-haiku-20240307' },
                                ]}
                                onChange={(value) => updateSetting('anthropic_model', value)}
                                __nextHasNoMarginBottom
                            />
                        </PanelRow>
                    </PanelBody>
                </Panel>

                <Panel>
                    <PanelBody title="Webhook & Security" initialOpen={false}>
                        <PanelRow>
                            <TextControl
                                label="Webhook Secret"
                                help="Secret key for webhook signature verification (optional)"
                                value={settings.webhook_secret}
                                onChange={(value) => updateSetting('webhook_secret', value)}
                                type="password"
                                __nextHasNoMarginBottom
                            />
                        </PanelRow>
                    </PanelBody>
                </Panel>

                <Panel>
                    <PanelBody title="Sync Options" initialOpen={false}>
                        <PanelRow>
                            <ToggleControl
                                label="Auto Sync"
                                help="Automatically sync pages with Siloq platform"
                                checked={settings.auto_sync}
                                onChange={(checked) => updateSetting('auto_sync', checked)}
                                __nextHasNoMarginBottom
                            />
                        </PanelRow>

                        <PanelRow>
                            <ToggleControl
                                label="Sync on Save"
                                help="Automatically sync when pages are saved/updated"
                                checked={settings.sync_on_save}
                                onChange={(checked) => updateSetting('sync_on_save', checked)}
                                __nextHasNoMarginBottom
                            />
                        </PanelRow>
                    </PanelBody>
                </Panel>

                <HStack spacing={2} justify="flex-start">
                    <Button
                        variant="primary"
                        onClick={saveSettings}
                        isBusy={isSaving}
                        disabled={isSaving || !hasChanges}
                    >
                        {isSaving ? 'Saving...' : 'Save Changes'}
                    </Button>

                    {hasChanges && (
                        <Text variant="muted">You have unsaved changes</Text>
                    )}
                </HStack>
            </VStack>
        </div>
    );
}
