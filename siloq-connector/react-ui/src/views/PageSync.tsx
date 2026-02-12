import React, { useState } from 'react';
import { RefreshCw, CheckCircle, FileText, Search, Filter } from 'lucide-react';
import { Page } from '../types';

interface PageSyncProps {
  pages: Page[];
  setPages: (pages: Page[]) => void;
  api: { post: (endpoint: string, data: any) => Promise<any> };
}

export const PageSync: React.FC<PageSyncProps> = ({ pages, setPages, api }) => {
  const [selectedPages, setSelectedPages] = useState<number[]>([]);
  const [filter, setFilter] = useState('all');
  const [isSyncing, setIsSyncing] = useState(false);

  const filteredPages = pages.filter(page => {
    if (filter === 'synced') return page.synced;
    if (filter === 'pending') return !page.synced;
    return true;
  });

  const handleSync = async () => {
    if (selectedPages.length === 0) return;
    
    setIsSyncing(true);
    try {
      await api.post('/sync', { pageIds: selectedPages });
      
      // Update local state
      const updatedPages = pages.map(p => 
        selectedPages.includes(p.id) 
          ? { ...p, synced: true, syncedAt: new Date().toISOString() }
          : p
      );
      setPages(updatedPages);
      setSelectedPages([]);
    } catch (e) {
      console.error('Sync failed', e);
      alert('Sync failed. Please try again.');
    } finally {
      setIsSyncing(false);
    }
  };

  const toggleSelection = (id: number) => {
    setSelectedPages(prev => 
      prev.includes(id) ? prev.filter(p => p !== id) : [...prev, id]
    );
  };

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-normal text-gray-800">Page Synchronization</h1>
          <p className="text-sm text-gray-500 mt-1">Import your WordPress pages to Siloq for analysis.</p>
        </div>
        <button
          onClick={handleSync}
          disabled={selectedPages.length === 0 || isSyncing}
          className="px-4 py-2 bg-[#2271b1] text-white rounded text-sm font-medium hover:bg-[#135e96] disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
        >
          {isSyncing ? (
            <RefreshCw className="animate-spin" size={16} />
          ) : (
            <RefreshCw size={16} />
          )}
          Bulk Sync ({selectedPages.length})
        </button>
      </div>

      {/* Filters */}
      <div className="bg-white p-4 rounded shadow-sm flex items-center gap-4">
        <div className="flex items-center gap-2 text-sm">
          <Filter size={16} className="text-gray-400" />
          <button 
            onClick={() => setFilter('all')}
            className={`px-3 py-1 rounded ${filter === 'all' ? 'bg-[#2271b1] text-white' : 'text-gray-600 hover:bg-gray-100'}`}
          >
            All ({pages.length})
          </button>
          <button 
            onClick={() => setFilter('synced')}
            className={`px-3 py-1 rounded ${filter === 'synced' ? 'bg-[#2271b1] text-white' : 'text-gray-600 hover:bg-gray-100'}`}
          >
            Synced ({pages.filter(p => p.synced).length})
          </button>
          <button 
            onClick={() => setFilter('pending')}
            className={`px-3 py-1 rounded ${filter === 'pending' ? 'bg-[#2271b1] text-white' : 'text-gray-600 hover:bg-gray-100'}`}
          >
            Pending ({pages.filter(p => !p.synced).length})
          </button>
        </div>
        <div className="flex-1 relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={16} />
          <input 
            type="text" 
            placeholder="Search pages..."
            className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded text-sm"
          />
        </div>
      </div>

      {/* Table */}
      <div className="bg-white rounded shadow-sm overflow-hidden">
        <table className="w-full">
          <thead className="bg-gray-50 border-b border-gray-200">
            <tr>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Author</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
              <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {filteredPages.map((page) => (
              <tr key={page.id} className="hover:bg-gray-50">
                <td className="px-6 py-4">
                  <div className="flex items-center gap-3">
                    <input 
                      type="checkbox"
                      checked={selectedPages.includes(page.id)}
                      onChange={() => toggleSelection(page.id)}
                      className="rounded border-gray-300"
                    />
                    <FileText size={16} className="text-gray-400" />
                    <div>
                      <p className="font-medium text-[#2271b1] hover:underline cursor-pointer">{page.title}</p>
                      <p className="text-xs text-gray-500">#{page.id}</p>
                    </div>
                  </div>
                </td>
                <td className="px-6 py-4 text-sm text-gray-600">{page.author}</td>
                <td className="px-6 py-4 text-sm text-gray-500">
                  {page.status === 'publish' ? 'Published' : 'Draft'}<br />
                  <span className="text-xs">{page.modified?.split(' ')[0]}</span>
                </td>
                <td className="px-6 py-4">
                  {page.synced ? (
                    <span className="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">
                      <CheckCircle size={12} className="mr-1" /> Synced
                    </span>
                  ) : (
                    <span className="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-600">
                      <FileText size={12} className="mr-1" /> Not Synced
                    </span>
                  )}
                </td>
                <td className="px-6 py-4 text-right">
                  <button
                    onClick={() => toggleSelection(page.id)}
                    className={`px-3 py-1 rounded text-xs font-medium ${
                      selectedPages.includes(page.id)
                        ? 'bg-[#2271b1] text-white'
                        : 'border border-[#2271b1] text-[#2271b1] hover:bg-[#2271b1] hover:text-white'
                    }`}
                  >
                    {selectedPages.includes(page.id) ? 'Selected' : page.synced ? 'Re-sync' : 'Sync Now'}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="text-xs text-gray-500">
        Showing {filteredPages.length} items
      </div>
    </div>
  );
};
