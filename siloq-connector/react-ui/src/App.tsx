import React, { useState, useEffect } from 'react';
import { LayoutDashboard, Sparkles, RefreshCw, ScanLine, Settings, Menu, X } from 'lucide-react';
import { Dashboard } from './views/Dashboard';
import { SetupWizard } from './views/SetupWizard';
import { PageSync } from './views/PageSync';
import { LeadGenScanner } from './views/LeadGenScanner';
import { SettingsView } from './views/Settings';
import { AppView, Config, Page } from './types';

// WordPress data from localized script
const wpData = (window as any).siloqData || {
  restUrl: '/wp-json/siloq/v1',
  nonce: '',
  initialView: 'dashboard',
  apiKey: '',
  siteId: '',
  connected: false,
  autoSync: false,
  wizardCompleted: false,
};

const DEFAULT_CONFIG: Config = {
  apiKey: wpData.apiKey || '',
  siteId: wpData.siteId || '',
  connected: wpData.connected || false,
  autoSync: wpData.autoSync || false,
  wizardCompleted: wpData.wizardCompleted || false,
};

// API helper
export const api = {
  async get(endpoint: string) {
    const response = await fetch(`${wpData.restUrl}${endpoint}`, {
      headers: { 'X-WP-Nonce': wpData.nonce },
    });
    if (!response.ok) throw new Error(`API error: ${response.status}`);
    return response.json();
  },
  
  async post(endpoint: string, data: any) {
    const response = await fetch(`${wpData.restUrl}${endpoint}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpData.nonce,
      },
      body: JSON.stringify(data),
    });
    if (!response.ok) throw new Error(`API error: ${response.status}`);
    return response.json();
  },
};

const App: React.FC = () => {
  const [currentView, setCurrentView] = useState<AppView>(() => {
    const viewMap: Record<string, AppView> = {
      'dashboard': AppView.DASHBOARD,
      'wizard': AppView.WIZARD,
      'sync': AppView.SYNC,
      'scanner': AppView.SCANNER,
      'settings': AppView.SETTINGS,
    };
    return viewMap[wpData.initialView] || AppView.DASHBOARD;
  });
  
  const [sidebarOpen, setSidebarOpen] = useState(true);
  const [config, setConfig] = useState<Config>(DEFAULT_CONFIG);
  const [pages, setPages] = useState<Page[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      const [configData, pagesData] = await Promise.all([
        api.get('/config'),
        api.get('/pages'),
      ]);
      
      setConfig({
        apiKey: configData.apiKey || '',
        siteId: configData.siteId || '',
        connected: configData.connected || false,
        autoSync: configData.autoSync || false,
        wizardCompleted: configData.wizardCompleted || false,
      });
      setPages(pagesData || []);
    } catch (e) {
      console.error('Failed to load data', e);
    } finally {
      setIsLoading(false);
    }
  };

  const handleWizardComplete = async (newConfig: Config) => {
    try {
      await api.post('/wizard/complete', { apiKey: newConfig.apiKey, siteId: newConfig.siteId });
      setConfig(newConfig);
      setCurrentView(AppView.DASHBOARD);
    } catch (e) {
      console.error('Failed to complete wizard', e);
    }
  };

  const handleUpdateConfig = async (newConfig: Partial<Config>) => {
    try {
      await api.post('/config', newConfig);
      setConfig(prev => ({ ...prev, ...newConfig }));
    } catch (e) {
      console.error('Failed to update config', e);
    }
  };

  const handleDisconnect = async () => {
    try {
      await api.post('/disconnect', {});
      setConfig({ apiKey: '', siteId: '', connected: false, autoSync: false, wizardCompleted: false });
      setCurrentView(AppView.DASHBOARD);
    } catch (e) {
      console.error('Failed to disconnect', e);
    }
  };

  const navItems = [
    { view: AppView.DASHBOARD, label: 'Dashboard', icon: LayoutDashboard },
    { view: AppView.WIZARD, label: 'Setup Wizard', icon: Sparkles },
    { view: AppView.SYNC, label: 'Page Sync', icon: RefreshCw },
    { view: AppView.SCANNER, label: 'Lead Gen Scanner', icon: ScanLine },
    { view: AppView.SETTINGS, label: 'Settings', icon: Settings },
  ];

  const renderContent = () => {
    if (isLoading) {
      return (
        <div className="flex items-center justify-center h-64">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#2271b1]"></div>
        </div>
      );
    }

    switch (currentView) {
      case AppView.DASHBOARD:
        return <Dashboard config={config} pages={pages} onChangeView={setCurrentView} />;
      case AppView.WIZARD:
        return <SetupWizard config={config} onComplete={handleWizardComplete} />;
      case AppView.SYNC:
        return <PageSync pages={pages} setPages={setPages} api={api} />;
      case AppView.SCANNER:
        return <LeadGenScanner pages={pages} api={api} />;
      case AppView.SETTINGS:
        return <SettingsView config={config} onUpdateConfig={handleUpdateConfig} onDisconnect={handleDisconnect} />;
      default:
        return <Dashboard config={config} pages={pages} onChangeView={setCurrentView} />;
    }
  };

  return (
    <div className="min-h-screen p-4 flex items-center justify-center">
      <div className="w-full max-w-7xl min-h-[85vh] rounded-xl overflow-hidden flex">
        {/* Main Content */}
        <main className="flex-1 p-6 overflow-auto">
          {renderContent()}
          
          {/* Footer */}
          <div className="mt-12 py-4 border-t border-gray-300 text-xs text-gray-500 flex justify-end">
            <span>Version 1.0.0</span>
          </div>
        </main>
      </div>
    </div>
  );
};

export default App;
