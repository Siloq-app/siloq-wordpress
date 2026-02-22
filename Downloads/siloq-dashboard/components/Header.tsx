'use client'

import { useState } from 'react'
import { Shield, ChevronDown, Check } from 'lucide-react'
import { AutomationMode } from './Dashboard'

interface HeaderProps {
  automationMode: AutomationMode
  onAutomationChange: (mode: AutomationMode) => void
}

const automationModes = [
  { id: 'manual' as const, label: 'Manual', desc: 'All changes require approval' },
  { id: 'semi' as const, label: 'Semi-Auto', desc: 'Safe changes auto-execute' },
  { id: 'full' as const, label: 'Full-Auto', desc: '48-hour rollback window' },
]

export default function Header({ automationMode, onAutomationChange }: HeaderProps) {
  const [showDropdown, setShowDropdown] = useState(false)

  return (
    <header className="px-8 py-5 border-b border-slate-700/50 flex items-center justify-between">
      <div className="flex items-center gap-3">
        <div className="w-10 h-10 bg-gradient-to-br from-blue-500 to-violet-500 rounded-xl flex items-center justify-center font-mono font-bold text-lg">
          S
        </div>
        <span className="text-xl font-bold tracking-tight">Siloq</span>
        <span className="text-[11px] px-2 py-1 bg-indigo-500/20 rounded text-indigo-300 font-semibold">
          V1
        </span>
      </div>

      <div className="flex items-center gap-5">
        {/* Automation Mode Selector */}
        <div className="relative">
          <button
            onClick={() => setShowDropdown(!showDropdown)}
            className="flex items-center gap-2 bg-slate-800/80 border border-slate-700/50 rounded-lg px-3 py-2 hover:border-slate-600 transition-colors"
          >
            <Shield size={14} className="text-slate-400" />
            <span className="text-sm text-slate-300">Automation:</span>
            <span className={`text-[10px] px-2 py-0.5 rounded font-semibold uppercase automation-${automationMode}`}>
              {automationMode === 'manual' ? 'Manual' : automationMode === 'semi' ? 'Semi-Auto' : 'Full-Auto'}
            </span>
            <ChevronDown size={14} className="text-slate-400" />
          </button>

          {showDropdown && (
            <div className="absolute top-full right-0 mt-2 bg-slate-800/95 border border-slate-700/50 rounded-xl p-2 w-72 z-50 shadow-2xl">
              {automationModes.map((mode) => (
                <button
                  key={mode.id}
                  onClick={() => { onAutomationChange(mode.id); setShowDropdown(false) }}
                  className={`w-full flex items-center justify-between p-3 rounded-lg text-left transition-colors ${
                    automationMode === mode.id ? 'bg-indigo-500/10' : 'hover:bg-slate-700/50'
                  }`}
                >
                  <div>
                    <div className="text-sm font-medium">{mode.label}</div>
                    <div className="text-xs text-slate-500">{mode.desc}</div>
                  </div>
                  {automationMode === mode.id && (
                    <Check size={16} className="text-indigo-400" />
                  )}
                </button>
              ))}
            </div>
          )}
        </div>

        {/* Site Status */}
        <div className="flex items-center gap-2">
          <span className="text-sm text-slate-400">yoursite.com</span>
          <div className="w-2 h-2 bg-emerald-400 rounded-full shadow-lg shadow-emerald-400/50" />
        </div>
      </div>
    </header>
  )
}
