import React, { useState } from 'react';
import { Key, Settings, CheckCircle, ArrowRight, Loader2, ArrowLeft, Pencil } from 'lucide-react';
import { Config } from '../types';

interface SetupWizardProps {
  config: Config;
  onComplete: (config: Config) => void;
}

export const SetupWizard: React.FC<SetupWizardProps> = ({ config, onComplete }) => {
  const [step, setStep] = useState(1);
  const [apiKey, setApiKey] = useState(config.apiKey);
  const [isConnecting, setIsConnecting] = useState(false);
  const [autoSync, setAutoSync] = useState(true);
  const [isApiKeyVerified, setIsApiKeyVerified] = useState(false);
  const [isEditingKey, setIsEditingKey] = useState(false);

  const handleConnect = async () => {
    if (!apiKey.trim()) return;
    
    setIsConnecting(true);
    // Simulate API verification
    await new Promise(resolve => setTimeout(resolve, 1500));
    setIsConnecting(false);
    setIsApiKeyVerified(true);
    setIsEditingKey(false);
    setStep(2);
  };

  const handleChangeKey = () => {
    setIsEditingKey(true);
    setIsApiKeyVerified(false);
  };

  const handleBackToStep1 = () => {
    setStep(1);
  };

  const handleComplete = () => {
    onComplete({
      ...config,
      apiKey,
      connected: true,
      autoSync,
      wizardCompleted: true,
    });
  };

  const steps = [
    { num: 1, label: 'Connect API' },
    { num: 2, label: 'Sync Settings' },
    { num: 3, label: 'Complete' },
  ];

  return (
    <div className="max-w-2xl mx-auto">
      {/* Progress */}
      <div className="flex items-center justify-center mb-8">
        {steps.map((s, i) => (
          <React.Fragment key={s.num}>
            <div className="flex flex-col items-center">
              <div className={`w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold ${
                step >= s.num ? 'bg-[#2271b1] text-white' : 'bg-gray-200 text-gray-500'
              }`}>
                {s.num}
              </div>
              <span className={`text-xs mt-2 ${step >= s.num ? 'text-[#2271b1]' : 'text-gray-400'}`}>
                {s.label}
              </span>
            </div>
            {i < steps.length - 1 && (
              <div className={`w-24 h-0.5 mx-2 ${step > s.num ? 'bg-[#2271b1]' : 'bg-gray-200'}`} />
            )}
          </React.Fragment>
        ))}
      </div>

      {/* Content */}
      <div className="bg-white p-8 rounded shadow-sm">
        {step === 1 && (
          <div className="text-center">
            <div className="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <Key className="text-[#2271b1]" size={28} />
            </div>
            <h2 className="text-xl font-semibold text-gray-800 mb-2">Connect to Siloq Cloud</h2>
            <p className="text-sm text-gray-500 mb-6">Enter your API Key to verify your license and enable features.</p>
            
            <div className="max-w-md mx-auto text-left">
              <label className="block text-sm font-medium text-gray-700 mb-1">
                {isApiKeyVerified && !isEditingKey ? 'Active API Key' : 'API Key'}
              </label>
              <div className="flex rounded shadow-sm">
                {isApiKeyVerified && !isEditingKey ? (
                  <span className="inline-flex items-center px-3 rounded-l border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
                    <CheckCircle size={14} className="text-green-600 mr-1" /> Verified
                  </span>
                ) : null}
                <input
                  type="password"
                  value={apiKey}
                  onChange={(e) => setApiKey(e.target.value)}
                  disabled={isApiKeyVerified && !isEditingKey}
                  placeholder="sl_live_..."
                  className={`flex-1 px-3 py-2 border border-gray-300 ${isApiKeyVerified && !isEditingKey ? 'bg-gray-50 text-gray-500' : 'bg-white'} ${isApiKeyVerified && !isEditingKey ? 'rounded-r' : 'rounded'} text-sm`}
                />
              </div>
              <p className="text-xs text-gray-400 mt-1">
                {isApiKeyVerified && !isEditingKey 
                  ? 'API key verified. Click "Change Key" to update.'
                  : 'Found in your Siloq dashboard settings.'}
              </p>
              
              {isApiKeyVerified && !isEditingKey ? (
                <button
                  onClick={handleChangeKey}
                  className="w-full mt-4 px-4 py-2 border border-gray-300 text-gray-700 rounded text-sm font-medium hover:bg-gray-50 flex items-center justify-center gap-2"
                >
                  <Pencil size={16} />
                  Change Key
                </button>
              ) : (
                <button
                  onClick={handleConnect}
                  disabled={!apiKey.trim() || isConnecting}
                  className="w-full mt-4 px-4 py-2 bg-[#2271b1] text-white rounded text-sm font-medium hover:bg-[#135e96] disabled:opacity-50 flex items-center justify-center gap-2"
                >
                  {isConnecting ? (
                    <>
                      <Loader2 className="animate-spin" size={16} />
                      Verifying...
                    </>
                  ) : (
                    <>
                      Verify & Connect <ArrowRight size={16} />
                    </>
                  )}
                </button>
              )}
            </div>
          </div>
        )}

        {step === 2 && (
          <div className="text-center">
            <div className="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <Settings className="text-purple-600" size={28} />
            </div>
            <h2 className="text-xl font-semibold text-gray-800 mb-2">Sync Settings</h2>
            <p className="text-sm text-gray-500 mb-6">Configure how Siloq syncs with your WordPress content.</p>
            
            <div className="max-w-md mx-auto text-left space-y-4">
              <div className="flex items-center justify-between p-4 bg-gray-50 rounded">
                <div>
                  <h4 className="font-medium text-gray-800">Auto Sync</h4>
                  <p className="text-xs text-gray-500">Automatically sync when publishing content</p>
                </div>
                <button
                  onClick={() => setAutoSync(!autoSync)}
                  className={`relative inline-flex h-6 w-11 rounded-full transition-colors ${autoSync ? 'bg-[#2271b1]' : 'bg-gray-200'}`}
                >
                  <span className={`inline-block h-6 w-6 transform rounded-full bg-white shadow transition-transform ${autoSync ? 'translate-x-5' : 'translate-x-0.5'}`} />
                </button>
              </div>

              <div className="flex items-center justify-between p-4 bg-gray-50 rounded">
                <div>
                  <h4 className="font-medium text-gray-800">Content Types</h4>
                  <p className="text-xs text-gray-500">Posts and Pages will be synced</p>
                </div>
                <span className="text-sm text-gray-600">Posts, Pages</span>
              </div>

              <div className="flex items-center gap-3">
                <button
                  onClick={handleBackToStep1}
                  className="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded text-sm font-medium hover:bg-gray-50 flex items-center justify-center gap-2"
                >
                  <ArrowLeft size={16} />
                  Back
                </button>
                <button
                  onClick={() => setStep(3)}
                  className="flex-1 px-4 py-2 bg-[#2271b1] text-white rounded text-sm font-medium hover:bg-[#135e96] flex items-center justify-center gap-2"
                >
                  Continue <ArrowRight size={16} />
                </button>
              </div>
            </div>
          </div>
        )}

        {step === 3 && (
          <div className="text-center">
            <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <CheckCircle className="text-green-600" size={28} />
            </div>
            <h2 className="text-xl font-semibold text-gray-800 mb-2">Setup Complete!</h2>
            <p className="text-sm text-gray-500 mb-6">Your WordPress site is now connected to Siloq.</p>
            
            <div className="max-w-md mx-auto">
              <div className="bg-green-50 border border-green-200 p-4 rounded mb-6 text-left">
                <h4 className="font-medium text-green-800 mb-2">What's next?</h4>
                <ul className="text-sm text-green-700 space-y-1">
                  <li>• Sync your existing pages</li>
                  <li>• Run the Lead Gen Scanner</li>
                  <li>• Explore AI-powered recommendations</li>
                </ul>
              </div>

              <button
                onClick={handleComplete}
                className="w-full px-4 py-2 bg-[#2271b1] text-white rounded text-sm font-medium hover:bg-[#135e96]"
              >
                Go to Dashboard
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};
