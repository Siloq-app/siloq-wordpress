import React, { useState } from 'react';
import { ScanLine, Sparkles, Target, AlertCircle } from 'lucide-react';
import { Page, ScanResult } from '../types';

interface LeadGenScannerProps {
  pages: Page[];
  api: { post: (endpoint: string, data: any) => Promise<any> };
}

export const LeadGenScanner: React.FC<LeadGenScannerProps> = ({ pages, api }) => {
  const [selectedPage, setSelectedPage] = useState<number | ''>('');
  const [isScanning, setIsScanning] = useState(false);
  const [result, setResult] = useState<ScanResult | null>(null);

  const handleScan = async () => {
    if (!selectedPage) return;
    
    setIsScanning(true);
    setResult(null);
    
    try {
      const page = pages.find(p => p.id === selectedPage);
      const response = await api.post('/scan', { 
        pageId: selectedPage,
        content: page?.title || '' 
      });
      setResult(response.analysis);
    } catch (e) {
      console.error('Scan failed', e);
      alert('Scan failed. Please try again.');
    } finally {
      setIsScanning(false);
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-normal text-gray-800 flex items-center gap-2">
          Lead Gen Scanner
          <span className="px-2 py-0.5 bg-purple-100 text-purple-700 text-xs font-bold rounded">AI BETA</span>
        </h1>
        <p className="text-sm text-gray-500 mt-1">Analyze your content to maximize conversion rates and SEO performance.</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Left Panel */}
        <div className="space-y-4">
          <div className="bg-white p-6 rounded shadow-sm">
            <label className="block text-sm font-medium text-gray-700 mb-2">Select Page to Audit</label>
            <select
              value={selectedPage}
              onChange={(e) => setSelectedPage(e.target.value ? parseInt(e.target.value) : '')}
              className="w-full px-3 py-2 border border-gray-300 rounded text-sm"
            >
              <option value="">Choose a page...</option>
              {pages.map(page => (
                <option key={page.id} value={page.id}>{page.title}</option>
              ))}
            </select>

            <button
              onClick={handleScan}
              disabled={!selectedPage || isScanning}
              className="w-full mt-4 px-4 py-2 bg-[#2271b1] text-white rounded text-sm font-medium hover:bg-[#135e96] disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
              {isScanning ? (
                <>
                  <ScanLine className="animate-pulse" size={16} />
                  Running Analysis...
                </>
              ) : (
                <>
                  <ScanLine size={16} />
                  Run Analysis
                </>
              )}
            </button>

            <p className="text-xs text-center text-gray-400 mt-2">Powered by Gemini AI 5.0 Flash</p>
          </div>

          {/* Pro Tip */}
          <div className="bg-blue-50 border border-blue-200 p-4 rounded">
            <div className="flex items-start gap-2">
              <Sparkles className="text-blue-500 shrink-0" size={16} />
              <div>
                <h4 className="text-sm font-medium text-blue-800">Pro Tip</h4>
                <p className="text-xs text-blue-600 mt-1">
                  Pages with strong Call-to-Actions (CTAs) generally convert 3x better. Use the scanner to generate hook ideas.
                </p>
              </div>
            </div>
          </div>
        </div>

        {/* Right Panel - Results */}
        <div className="bg-white p-6 rounded shadow-sm border-2 border-dashed border-gray-200 min-h-[400px]">
          {result ? (
            <div className="space-y-6">
              {/* Score */}
              <div className="text-center">
                <div className="inline-flex items-center justify-center w-24 h-24 rounded-full bg-gradient-to-br from-green-400 to-blue-500 text-white text-3xl font-bold">
                  {result.score}
                </div>
                <p className="text-sm text-gray-500 mt-2">Overall Score</p>
              </div>

              {/* Lead Gen Score */}
              <div className="bg-gray-50 p-4 rounded">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium text-gray-700">Lead Generation Potential</span>
                  <span className="text-lg font-bold text-[#2271b1]">{result.leadGenScore}/100</span>
                </div>
                <div className="mt-2 h-2 bg-gray-200 rounded-full overflow-hidden">
                  <div 
                    className="h-full bg-[#2271b1] rounded-full"
                    style={{ width: `${result.leadGenScore}%` }}
                  />
                </div>
              </div>

              {/* Issues */}
              {result.issues.length > 0 && (
                <div>
                  <h4 className="text-sm font-medium text-gray-800 mb-2 flex items-center gap-2">
                    <AlertCircle size={16} className="text-yellow-500" />
                    Issues Found
                  </h4>
                  <ul className="space-y-2">
                    {result.issues.map((issue, i) => (
                      <li key={i} className="text-sm text-gray-600 flex items-start gap-2">
                        <span className={`w-2 h-2 rounded-full mt-1.5 ${
                          issue.type === 'error' ? 'bg-red-500' : 
                          issue.type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'
                        }`} />
                        {issue.message}
                      </li>
                    ))}
                  </ul>
                </div>
              )}

              {/* Recommendations */}
              <div>
                <h4 className="text-sm font-medium text-gray-800 mb-2 flex items-center gap-2">
                  <Target size={16} className="text-green-500" />
                  Recommendations
                </h4>
                <ul className="space-y-2">
                  {result.recommendations.map((rec, i) => (
                    <li key={i} className="text-sm text-gray-600 flex items-start gap-2">
                      <span className="text-green-500">✓</span>
                      {rec}
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          ) : (
            <div className="h-full flex items-center justify-center text-gray-400">
              <div className="text-center">
                <ScanLine size={48} className="mx-auto mb-3 opacity-50" />
                <p>Select a page and run the scanner to see results.</p>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
