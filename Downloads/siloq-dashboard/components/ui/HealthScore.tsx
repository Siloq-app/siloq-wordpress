'use client'

import { TrendingUp } from 'lucide-react'

interface Props {
  score: number
  change: number
}

export default function HealthScore({ score, change }: Props) {
  return (
    <div className="card p-7 text-center">
      <div className="text-xs text-slate-400 mb-4 uppercase tracking-widest">
        Content Health Score
      </div>
      <div className="relative w-36 h-36 mx-auto mb-5">
        <svg viewBox="0 0 100 100" className="-rotate-90">
          <circle
            cx="50"
            cy="50"
            r="45"
            fill="none"
            stroke="rgba(148, 163, 184, 0.1)"
            strokeWidth="8"
          />
          <circle
            cx="50"
            cy="50"
            r="45"
            fill="none"
            stroke="url(#scoreGradient)"
            strokeWidth="8"
            strokeLinecap="round"
            strokeDasharray={`${score * 2.83} 283`}
          />
          <defs>
            <linearGradient id="scoreGradient" x1="0%" y1="0%" x2="100%" y2="0%">
              <stop offset="0%" stopColor="#3b82f6" />
              <stop offset="100%" stopColor="#8b5cf6" />
            </linearGradient>
          </defs>
        </svg>
        <div className="absolute inset-0 flex flex-col items-center justify-center">
          <span className="text-4xl font-bold">{score}</span>
          <span className="text-xs text-slate-400">/ 100</span>
        </div>
      </div>
      <div className="flex items-center justify-center gap-1.5 text-emerald-400">
        <TrendingUp size={16} />
        <span className="text-sm">+{change} from last week</span>
      </div>
    </div>
  )
}
