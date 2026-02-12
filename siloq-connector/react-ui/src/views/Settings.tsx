import React, { useState } from 'react';
import { Settings, Check, Upload, LogOut, Package, Download, FileCode, Code2, Pencil } from 'lucide-react';
import { Config } from '../types';

interface SettingsProps {
  config: Config;
  onUpdateConfig: (config: Partial<Config>) => void;
  onDisconnect: () => void;
}

export const SettingsView: React.FC<SettingsProps> = ({ config, onUpdateConfig, onDisconnect }) => {
  const [isZipping, setIsZipping] = useState(false);
  const [isEditingKey, setIsEditingKey] = useState(false);
  const [tempKey, setTempKey] = useState(config.apiKey);

  const handleFileUpload = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
      const text = e.target?.result;
      if (typeof text === 'string' && text.trim()) {
        onUpdateConfig({ apiKey: text.trim(), connected: true });
        alert('API Key updated successfully.');
      }
    };
    reader.readAsText(file);
    event.target.value = '';
  };

  const handleSaveKey = () => {
    if (tempKey.trim()) {
      onUpdateConfig({ apiKey: tempKey.trim(), connected: true });
      setIsEditingKey(false);
    }
  };

  const handleCancelEdit = () => {
    setTempKey(config.apiKey);
    setIsEditingKey(false);
  };

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center gap-3">
        <Settings className="text-gray-400" size={28} />
        <h1 className="text-2xl font-normal text-gray-800">Plugin Settings</h1>
      </div>

      {/* Connection Status */}
      <div className="bg-white p-6 rounded shadow-sm">
        <h2 className="text-lg font-medium text-gray-800 border-b pb-2 mb-4">Connection Status</h2>
        
        <div className="space-y-4 max-w-lg">
          <div>
            <label className="block text-sm font-medium text-gray-700">{config.connected && !isEditingKey ? 'Active API Key' : 'API Key'}</label>
            <div className="mt-1 flex rounded shadow-sm">
              {config.connected && !isEditingKey ? (
                <span className="inline-flex items-center px-3 rounded-l border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
                  <Check size={14} className="text-green-600 mr-1" /> Verified
                </span>
              ) : null}
              <input
                type="password"
                value={isEditingKey ? tempKey : config.apiKey}
                onChange={(e) => setTempKey(e.target.value)}
                disabled={config.connected && !isEditingKey}
                placeholder="Enter your Siloq API key"
                className={`flex-1 px-3 py-2 border border-gray-300 ${config.connected && !isEditingKey ? 'bg-gray-50 text-gray-500' : 'bg-white text-gray-900'} ${config.connected && !isEditingKey ? 'rounded-r' : 'rounded-l'} focus:outline-none focus:ring-2 focus:ring-[#2271b1] focus:border-transparent`}
              />
            </div>
            <p className="mt-1 text-xs text-gray-500">
              {config.connected && !isEditingKey 
                ? 'API key verified and active. Click "Change API Key" to update.'
                : 'Enter your API key from the Siloq dashboard.'}
            </p>
          </div>

          {config.connected && !isEditingKey ? (
            <div className="flex items-center gap-3">
              <button
                onClick={() => setIsEditingKey(true)}
                className="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded text-sm font-medium hover:bg-gray-50"
              >
                <Pencil size={16} className="mr-2" />
                Change API Key
              </button>
            </div>
          ) : isEditingKey ? (
            <div className="flex items-center gap-3">
              <button
                onClick={handleSaveKey}
                className="inline-flex items-center px-4 py-2 bg-[#2271b1] text-white rounded text-sm font-medium hover:bg-[#1e5f94]"
              >
                <Check size={16} className="mr-2" />
                Verify & Connect
              </button>
              <button
                onClick={handleCancelEdit}
                className="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded text-sm font-medium hover:bg-gray-50"
              >
                Cancel
              </button>
            </div>
          ) : null}

          <div className="flex items-center justify-between py-2 flex-row-reverse">
            <div className="flex-1 text-right">
              <span className="block text-sm font-medium text-gray-800">Key File Upload</span>
              <p className="text-xs text-gray-500 mt-0.5">Accepts .txt or .key files containing your Siloq API key.</p>
            </div>
            <label className="cursor-pointer inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded text-gray-700 bg-white hover:bg-gray-50 mr-4">
              <Upload size={16} className="mr-2" />
              <span>Upload Key File</span>
              <input type="file" className="sr-only" accept=".txt,.key" onChange={handleFileUpload} />
            </label>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Synced Content Types</label>
            <div className="flex flex-wrap gap-2">
              <span className="px-3 py-1 rounded-full text-xs font-medium bg-[#2271b1]/10 text-[#2271b1] border border-[#2271b1]/20">
                Posts
              </span>
              <span className="px-3 py-1 rounded-full text-xs font-medium bg-[#2271b1]/10 text-[#2271b1] border border-[#2271b1]/20">
                Pages
              </span>
            </div>
          </div>

          <div className="flex items-center justify-between py-3">
            <div className="flex-1">
              <span className="block text-sm font-medium text-gray-800">Auto Sync</span>
              <p className="text-xs text-gray-500 mt-0.5">Automatically sync pages when published.</p>
            </div>
            <div className="flex items-center gap-3 ml-4">
              <span className={`text-sm font-medium ${config.autoSync ? 'text-green-600' : 'text-gray-400'}`}>
                {config.autoSync ? 'Enabled' : 'Disabled'}
              </span>
              <button
                onClick={() => onUpdateConfig({ autoSync: !config.autoSync })}
                className={`relative inline-flex h-6 w-11 rounded-full transition-colors focus:outline-none ${config.autoSync ? 'bg-[#2271b1]' : 'bg-gray-200'}`}
                role="switch"
                aria-checked={config.autoSync}
              >
                <span className={`inline-block h-6 w-6 transform rounded-full bg-white shadow transition-transform ${config.autoSync ? 'translate-x-5' : 'translate-x-0.5'}`} />
              </button>
            </div>
          </div>

          <button
            onClick={onDisconnect}
            className="inline-flex items-center px-4 py-2 border border-red-300 text-red-700 rounded text-sm font-medium hover:bg-red-50"
          >
            <LogOut size={16} className="mr-2" />
            Disconnect Account
          </button>
        </div>
      </div>

      {/* Download Plugin Package */}
      <div className="bg-[#2271b1] p-6 rounded shadow-md text-white">
        <h2 className="text-xl font-bold mb-2 flex items-center gap-2 text-white">
          <Package size={24} />
          Download Plugin Package
        </h2>
        <p className="text-sm text-blue-100 mb-4">
          Export the complete plugin package for installation on another WordPress site.
        </p>
        
        <div className="bg-white/10 p-4 rounded border border-white/20 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-white/90 text-[#2271b1] rounded">
              <FileCode size={24} />
            </div>
            <div>
              <div className="text-sm font-bold">siloq-connector.zip</div>
              <div className="text-xs text-blue-200">v1.0.0 • PHP 7.4+ • React 19</div>
            </div>
          </div>
          <button className="px-6 py-2 bg-white text-[#2271b1] rounded shadow font-bold text-sm hover:bg-blue-50 flex items-center gap-2">
            <Download size={18} />
            Download ZIP
          </button>
        </div>
      </div>
    </div>
  );
};
