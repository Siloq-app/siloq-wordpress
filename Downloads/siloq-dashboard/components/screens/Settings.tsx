'use client'

import { Check } from 'lucide-react'
import { AutomationMode } from '../Dashboard'

interface Props {
  automationMode: AutomationMode
  onAutomationChange: (mode: AutomationMode) => void
}

const automationModes = [
  { 
    id: 'manual' as const, 
    label: 'Manual', 
    desc: 'All changes require explicit approval before execution' 
  },
  { 
    id: 'semi' as const, 
    label: 'Semi-Auto', 
    desc: 'Safe changes auto-execute immediately. Destructive changes require explicit approval.' 
  },
  { 
    id: 'full' as const, 
    label: 'Full-Auto', 
    desc: 'All changes auto-execute immediately. 48-hour rollback window on destructive changes. Daily digest email notification.' 
  },
]

export default function Settings({ automationMode, onAutomationChange }: Props) {
  return (
    <div className="card p-7">
      <h2 className="text-2xl font-bold mb-8">Settings</h2>

      {/* Automation Preferences */}
      <div className="mb-8">
        <h3 className="text-base font-semibold mb-4">Automation Preferences</h3>
        <div className="space-y-3">
          {automationModes.map((mode) => (
            <div
              key={mode.id}
              onClick={() => {
  if (onAutomationChange) {
    onAutomationChange(mode.id);
  }
}}
              className={`p-5 rounded-xl cursor-pointer transition-all flex items-center justify-between ${
                automationMode === mode.id
                  ? 'bg-indigo-500/10 border-2 border-indigo-500/50'
                  : 'bg-slate-900/40 border border-slate-700/30 hover:border-slate-600'
              }`}
            >
              <div>
                <div className="text-base font-semibold mb-1">{mode.label}</div>
                <div className="text-sm text-slate-400">{mode.desc}</div>
              </div>
              {automationMode === mode.id && (
                <div className="w-6 h-6 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-full flex items-center justify-center">
                  <Check size={14} className="text-white" />
                </div>
              )}
            </div>
          ))}
        </div>
      </div>

      {/* Change Classification Legend */}
      <div className="bg-slate-900/40 rounded-xl p-5 border border-slate-700/30">
        <h4 className="text-sm font-semibold mb-4">Change Classification Reference</h4>
        <div className="grid grid-cols-2 gap-6">
          <div>
            <div className="text-xs text-emerald-400 font-semibold mb-2">✓ SAFE (can auto-approve)</div>
            <div className="text-sm text-slate-400 space-y-1">
              <div>• Internal link additions</div>
              <div>• Entity assignments</div>
              <div>• New content generation</div>
              <div>• Anchor text optimization</div>
              <div>• Schema markup updates</div>
            </div>
          </div>
          <div>
            <div className="text-xs text-red-400 font-semibold mb-2">⚠ DESTRUCTIVE (approval or rollback)</div>
            <div className="text-sm text-slate-400 space-y-1">
              <div>• URL redirects (301s)</div>
              <div>• Page deletions/archival</div>
              <div>• Content merges</div>
              <div>• Keyword reassignment</div>
              <div>• Silo restructuring</div>
            </div>
          </div>
        </div>
      </div>

      {/* Notification Preferences */}
      <div className="mt-8">
        <h3 className="text-base font-semibold mb-4">Notification Preferences</h3>
        <div className="space-y-3">
          {[
            { label: 'Daily digest email (Full-Auto mode)', checked: true },
            { label: 'Immediate alerts for BLOCK errors', checked: true },
            { label: 'Weekly governance report', checked: false },
          ].map((pref, i) => (
            <div
              key={i}
              className="flex items-center justify-between p-4 bg-slate-900/40 rounded-lg border border-slate-700/30"
            >
              <span className="text-sm text-slate-300">{pref.label}</span>
              <div className={`w-10 h-6 rounded-full p-1 cursor-pointer transition-colors ${
                pref.checked ? 'bg-indigo-500' : 'bg-slate-700'
              }`}>
                <div className={`w-4 h-4 bg-white rounded-full transition-transform ${
                  pref.checked ? 'translate-x-4' : ''
                }`} />
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}
