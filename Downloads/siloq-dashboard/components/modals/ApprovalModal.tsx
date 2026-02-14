'use client'

import { X, Check } from 'lucide-react'

interface Props {
  onClose: () => void
}

export default function ApprovalModal({ onClose }: Props) {
  return (
    <div 
      className="fixed inset-0 bg-black/80 flex items-center justify-center z-50"
      onClick={onClose}
    >
      <div 
        className="card w-[600px] p-8"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center justify-between mb-6">
          <h2 className="text-xl font-bold">Siloq Recommendation</h2>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-200">
            <X size={20} />
          </button>
        </div>

        {/* Issue Summary */}
        <div className="bg-red-500/10 border border-red-500/20 rounded-xl p-5 mb-6">
          <div className="text-xs text-red-400 font-semibold mb-2">CANNIBALIZATION DETECTED</div>
          <div className="text-lg font-semibold mb-1">3 pages competing for "kitchen remodeling"</div>
          <div className="text-sm text-slate-400">Splitting 12,400 monthly impressions across URLs</div>
        </div>

        {/* Recommended Fix */}
        <div className="mb-6">
          <div className="text-sm font-semibold mb-3">Recommended Fix:</div>
          <div className="bg-slate-900/60 rounded-lg p-4 space-y-3">
            <div className="text-sm">
              <span className="text-emerald-400 mr-2">1.</span>
              Designate <code className="bg-indigo-500/20 px-1.5 py-0.5 rounded text-indigo-300">/kitchen-remodel-guide</code> as Target Page
            </div>
            <div className="text-sm">
              <span className="text-emerald-400 mr-2">2.</span>
              Redirect <code className="bg-indigo-500/20 px-1.5 py-0.5 rounded text-indigo-300">/remodel-your-kitchen</code> → Target (301)
            </div>
            <div className="text-sm">
              <span className="text-emerald-400 mr-2">3.</span>
              Differentiate <code className="bg-indigo-500/20 px-1.5 py-0.5 rounded text-indigo-300">/kitchen-remodel-cost</code> to target "cost" entities only
            </div>
          </div>
        </div>

        {/* Expected Outcome */}
        <div className="bg-emerald-500/10 rounded-lg p-4 mb-6">
          <div className="text-xs text-emerald-400 font-semibold mb-1">EXPECTED OUTCOME</div>
          <div className="text-sm text-slate-200">
            Consolidate ranking signals → single Target Page receives full 12,400 impression authority
          </div>
        </div>

        {/* Actions */}
        <div className="flex gap-3">
          <button className="btn-deny flex-1">Deny</button>
          <button className="btn-secondary flex-1">Modify</button>
          <button className="btn-approve flex-1 justify-center">
            <Check size={14} /> Approve All 3 Actions
          </button>
        </div>
      </div>
    </div>
  )
}
