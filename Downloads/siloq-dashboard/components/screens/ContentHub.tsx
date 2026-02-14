'use client'

import { Target, GitBranch, Zap, TrendingUp } from 'lucide-react'

interface Props {
  onGenerateClick: () => void
}

const contentActions = [
  { 
    title: 'Generate Target Page', 
    desc: 'Create a new pillar page (King) that will receive links from Supporting Pages', 
    icon: '👑', 
    color: 'amber' 
  },
  { 
    title: 'Generate Supporting Page', 
    desc: 'Create a Soldier page that links UP to a Target Page', 
    icon: '⚔️', 
    color: 'indigo' 
  },
  { 
    title: 'Differentiate Page', 
    desc: 'Rewrite content to target different entities and eliminate cannibalization', 
    icon: 'zap', 
    color: 'red' 
  },
  { 
    title: 'Fill Entity Gap', 
    desc: 'Generate content for entity clusters not yet covered in a silo', 
    icon: 'target', 
    color: 'emerald' 
  },
]

export default function ContentHub({ onGenerateClick }: Props) {
  const getIcon = (icon: string, color: string) => {
    if (icon === 'zap') return <Zap size={24} />
    if (icon === 'target') return <Target size={24} />
    return <span className="text-2xl">{icon}</span>
  }

  const getColorClasses = (color: string) => {
    const colors: Record<string, string> = {
      amber: 'bg-amber-500/15 text-amber-400',
      indigo: 'bg-indigo-500/15 text-indigo-400',
      red: 'bg-red-500/15 text-red-400',
      emerald: 'bg-emerald-500/15 text-emerald-400',
    }
    return colors[color] || colors.indigo
  }

  return (
    <div className="card p-7">
      <h2 className="text-2xl font-bold mb-2">Content Generation</h2>
      <p className="text-sm text-slate-400 mb-8">Generate content that fits your Reverse Silo architecture</p>

      <div className="grid grid-cols-2 gap-5">
        {contentActions.map((action, i) => (
          <div
            key={i}
            onClick={onGenerateClick}
            className="bg-slate-900/60 rounded-xl p-6 border border-slate-700/30 card-hover cursor-pointer"
          >
            <div className={`w-12 h-12 rounded-xl flex items-center justify-center mb-4 ${getColorClasses(action.color)}`}>
              {getIcon(action.icon, action.color)}
            </div>
            <h3 className="text-base font-semibold mb-2">{action.title}</h3>
            <p className="text-sm text-slate-400 leading-relaxed">{action.desc}</p>
          </div>
        ))}
      </div>

      {/* Terminal Animation Preview */}
      <div className="mt-8 bg-slate-900 rounded-xl p-6 border border-slate-700/30">
        <div className="text-xs text-slate-500 mb-3 uppercase tracking-wide">Agent Console Preview</div>
        <div className="font-mono text-sm space-y-1.5">
          <div className="text-emerald-400">&gt; Scanning site architecture…</div>
          <div className="text-blue-400">&gt; Locking primary intent…</div>
          <div className="text-violet-400">&gt; Enforcing entity inheritance…</div>
          <div className="text-amber-400">&gt; Blocking unauthorized outbound links…</div>
          <div className="text-emerald-400">&gt; Generating structured output…</div>
        </div>
        <div className="mt-4 text-xs text-slate-500">
          This terminal animation appears before content generation — differentiates Siloq from generic AI tools.
        </div>
      </div>
    </div>
  )
}
