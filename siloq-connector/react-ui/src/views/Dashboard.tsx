import React from 'react';
import { Zap, RefreshCw, AlertCircle, ArrowRight, CheckCircle, Clock } from 'lucide-react';
import { AppView, Config, Page } from '../types';

interface DashboardProps {
  config: Config;
  pages: Page[];
  onChangeView: (view: AppView) => void;
}

export const Dashboard: React.FC<DashboardProps> = ({ config, pages, onChangeView }) => {
  const syncedCount = pages.filter(p => p.synced).length;
  const recentActivity = pages.filter(p => p.synced).slice(0, 3);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-normal text-gray-800">Siloq Connector <span className="text-gray-500 text-lg">Dashboard</span></h1>
      </div>

      {/* Setup Required Banner */}
      {!config.connected && (
        <div className="bg-white border-l-4 border-yellow-500 p-4 rounded shadow-sm flex items-center justify-between">
          <div className="flex items-center gap-3">
            <AlertCircle className="text-yellow-500" size={24} />
            <div>
              <h3 className="font-medium text-gray-800">Action Required: Connect API</h3>
              <p className="text-sm text-gray-500">Please complete the setup wizard to unlock all features.</p>
            </div>
          </div>
          <button 
            onClick={() => onChangeView(AppView.WIZARD)}
            className="px-4 py-2 bg-[#2271b1] text-white rounded text-sm font-medium hover:bg-[#135e96]"
          >
            Run Setup Wizard
          </button>
        </div>
      )}

      {/* Quick Action Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="bg-white p-6 rounded shadow-sm">
          <div className="flex items-start gap-3">
            <div className="p-2 bg-blue-100 rounded">
              <Zap className="text-blue-600" size={20} />
            </div>
            <div>
              <h3 className="font-medium text-gray-800">Quick Scan</h3>
              <p className="text-sm text-gray-500 mt-1">Analyze your pages for lead generation opportunities.</p>
              <button 
                onClick={() => onChangeView(AppView.SCANNER)}
                className="mt-3 text-[#2271b1] text-sm hover:underline flex items-center gap-1"
              >
                Start Scanner <ArrowRight size={14} />
              </button>
            </div>
          </div>
        </div>

        <div className="bg-white p-6 rounded shadow-sm">
          <div className="flex items-start gap-3">
            <div className="p-2 bg-purple-100 rounded">
              <RefreshCw className="text-purple-600" size={20} />
            </div>
            <div>
              <h3 className="font-medium text-gray-800">Page Sync</h3>
              <p className="text-sm text-gray-500 mt-1">Sync WordPress content with Siloq backend.</p>
              <button 
                onClick={() => onChangeView(AppView.SYNC)}
                className="mt-3 text-[#2271b1] text-sm hover:underline flex items-center gap-1"
              >
                Manage Sync <ArrowRight size={14} />
              </button>
            </div>
          </div>
        </div>

        <div className="bg-white p-6 rounded shadow-sm">
          <div className="flex items-start gap-3">
            <div className="p-2 bg-green-100 rounded">
              <CheckCircle className="text-green-600" size={20} />
            </div>
            <div>
              <h3 className="font-medium text-gray-800">API Status</h3>
              <p className="text-sm text-gray-500 mt-1">
                Current Status: <span className={config.connected ? 'text-green-600 font-medium' : 'text-orange-500 font-medium'}>
                  {config.connected ? 'Connected' : 'Disconnected'}
                </span>
              </p>
              <button 
                onClick={() => onChangeView(AppView.SETTINGS)}
                className="mt-3 text-[#2271b1] text-sm hover:underline flex items-center gap-1"
              >
                Configure <ArrowRight size={14} />
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Recent Activity */}
      <div className="bg-white rounded shadow-sm">
        <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
          <h2 className="font-medium text-gray-800 flex items-center gap-2">
            <Clock size={18} />
            Recent Sync Activity
          </h2>
          <button 
            onClick={() => onChangeView(AppView.SYNC)}
            className="text-[#2271b1] text-sm hover:underline"
          >
            View All Pages
          </button>
        </div>
        
        <div className="divide-y divide-gray-100">
          {recentActivity.length > 0 ? recentActivity.map((page) => (
            <div key={page.id} className="px-6 py-4 flex items-center justify-between">
              <div className="flex items-center gap-3">
                <CheckCircle className="text-green-500" size={16} />
                <div>
                  <p className="font-medium text-gray-800">{page.title}</p>
                  <p className="text-xs text-gray-500">ID: {page.id} • {page.status}</p>
                </div>
              </div>
              <span className="text-xs text-gray-400">{page.syncedAt || page.modified}</span>
            </div>
          )) : (
            <div className="px-6 py-8 text-center text-gray-500">
              <p>No sync activity yet. Start by syncing your pages.</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
