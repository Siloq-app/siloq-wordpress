'use client'

import { Target, GitBranch, Clock, FileText, Link2, Settings } from 'lucide-react'
import { TabType } from './Dashboard'

interface SidebarProps {
  activeTab: TabType
  onTabChange: (tab: TabType) => void
  pendingCount: number
}

const navItems = [
  { id: 'dashboard' as const, icon: Target, label: 'Dashboard' },
  { id: 'silos' as const, icon: GitBranch, label: 'Silos' },
  { id: 'approvals' as const, icon: Clock, label: 'Approvals' },
  { id: 'content' as const, icon: FileText, label: 'Content' },
  { id: 'links' as const, icon: Link2, label: 'Internal Links' },
  { id: 'settings' as const, icon: Settings, label: 'Settings' },
]

export default function Sidebar({ activeTab, onTabChange, pendingCount }: SidebarProps) {
  return (
    <nav className="w-60 p-6 border-r border-slate-700/50">
      {navItems.map((item) => {
        const Icon = item.icon
        const isActive = activeTab === item.id
        const showBadge = item.id === 'approvals' && pendingCount > 0

        return (
          <button
            key={item.id}
            onClick={() => onTabChange(item.id)}
            className={`w-full flex items-center gap-3 px-4 py-3 mb-2 rounded-xl text-sm font-medium transition-all relative ${
              isActive
                ? 'bg-gradient-to-r from-blue-500 to-indigo-500 text-white'
                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/50'
            }`}
          >
            <Icon size={18} />
            {item.label}
            {showBadge && (
              <span className="absolute right-3 bg-red-500 text-white text-[11px] font-semibold px-2 py-0.5 rounded-full">
                {pendingCount}
              </span>
            )}
          </button>
        )
      })}
    </nav>
  )
}
