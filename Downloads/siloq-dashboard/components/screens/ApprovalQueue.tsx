'use client'

import { Check, RotateCcw, TrendingUp } from 'lucide-react'
import { PendingChange } from '../Dashboard'

interface Props {
  pendingChanges: PendingChange[]
}

export default function ApprovalQueue({ pendingChanges }: Props) {
  const safeChanges = pendingChanges.filter(c => c.risk === 'safe')
  const destructiveChanges = pendingChanges.filter(c => c.risk === 'destructive')

  return (
    <div className="card p-7">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h2 className="text-2xl font-bold mb-2">Approval Queue</h2>
          <p className="text-sm text-slate-400">
            Siloq-generated remediation plan — review and approve
          </p>
        </div>
        <div className="flex gap-3">
          <button className="btn-secondary">
            Approve All Safe ({safeChanges.length})
          </button>
          <button className="btn-primary">
            <Check size={14} /> Approve All
          </button>
        </div>
      </div>

      {/* Queue Stats */}
      <div className="grid grid-cols-3 gap-4 mb-6">
        <div className="bg-slate-900/40 rounded-lg p-4 border border-slate-700/30">
          <div className="text-xs text-slate-400 mb-1">Total Pending</div>
          <div className="text-2xl font-bold">{pendingChanges.length}</div>
        </div>
        <div className="bg-emerald-500/5 rounded-lg p-4 border border-emerald-500/20">
          <div className="text-xs text-emerald-400 mb-1">Safe Changes</div>
          <div className="text-2xl font-bold text-emerald-400">{safeChanges.length}</div>
        </div>
        <div className="bg-red-500/5 rounded-lg p-4 border border-red-500/20">
          <div className="text-xs text-red-400 mb-1">Destructive Changes</div>
          <div className="text-2xl font-bold text-red-400">{destructiveChanges.length}</div>
        </div>
      </div>

      {/* Change Cards */}
      <div className="space-y-4">
        {pendingChanges.map((change) => (
          <div
            key={change.id}
            className="bg-slate-900/60 rounded-xl p-6 border border-slate-700/30"
          >
            <div className="flex items-start justify-between">
              <div className="flex-1">
                <div className="flex items-center gap-3 mb-3">
                  <span className={change.risk === 'safe' ? 'risk-safe' : 'risk-destructive'}>
                    {change.risk === 'safe' ? '✓ Safe' : '⚠ Destructive'}
                  </span>
                  <span className="text-xs text-slate-500 uppercase">
                    {change.type.replace('_', ' ')}
                  </span>
                </div>

                <div className="text-base font-medium mb-2 text-slate-100">
                  {change.description}
                </div>

                <div className="text-sm text-slate-400 mb-2">
                  <span className="text-slate-500">DOCTRINE:</span> {change.doctrine}
                </div>

                <div className="text-sm text-emerald-400 flex items-center gap-1.5">
                  <TrendingUp size={14} />
                  Expected impact: {change.impact}
                </div>

                {change.risk === 'destructive' && (
                  <div className="mt-3 p-3 bg-red-500/10 rounded-lg text-xs text-red-300 flex items-center gap-2">
                    <RotateCcw size={14} />
                    48-hour rollback available after execution
                  </div>
                )}
              </div>

              <div className="flex gap-2 ml-6">
                <button className="btn-deny">Deny</button>
                <button className="btn-approve">
                  <Check size={14} /> Approve
                </button>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
