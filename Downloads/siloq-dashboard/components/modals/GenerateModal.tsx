'use client'

import { X, Zap } from 'lucide-react'
import { Silo } from '../Dashboard'

interface Props {
  silos: Silo[]
  onClose: () => void
}

export default function GenerateModal({ silos, onClose }: Props) {
  return (
    <div 
      className="fixed inset-0 bg-black/80 flex items-center justify-center z-50"
      onClick={onClose}
    >
      <div 
        className="card w-[500px] p-8"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center justify-between mb-6">
          <h2 className="text-xl font-bold">Generate Supporting Page</h2>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-200">
            <X size={20} />
          </button>
        </div>

        <div className="mb-5">
          <label className="text-sm text-slate-400 block mb-2">
            Target Silo (links UP to this Target Page)
          </label>
          <select className="w-full p-3 bg-slate-900/60 border border-slate-700/50 rounded-lg text-slate-200 text-sm">
            {silos.map((silo) => (
              <option key={silo.id}>
                👑 {silo.name} → {silo.targetPage.title}
              </option>
            ))}
          </select>
        </div>

        <div className="mb-5">
          <label className="text-sm text-slate-400 block mb-2">Content Type</label>
          <select className="w-full p-3 bg-slate-900/60 border border-slate-700/50 rounded-lg text-slate-200 text-sm">
            <option>Supporting Article (Soldier)</option>
            <option>FAQ Page</option>
            <option>How-To Guide</option>
            <option>Comparison Article</option>
          </select>
        </div>

        <div className="mb-6">
          <label className="text-sm text-slate-400 block mb-2">Target Entity Cluster</label>
          <input
            type="text"
            placeholder="e.g., kitchen lighting, under-cabinet lights, pendant lights"
            className="w-full p-3 bg-slate-900/60 border border-slate-700/50 rounded-lg text-slate-200 text-sm placeholder:text-slate-500"
          />
          <div className="text-[11px] text-slate-500 mt-1.5">
            Entity sources: NLP extraction • Google Knowledge Graph • GSC queries
          </div>
        </div>

        <div className="bg-indigo-500/10 rounded-lg p-4 mb-6">
          <div className="text-xs text-indigo-300 mb-2 font-semibold">✨ Siloq will automatically:</div>
          <div className="text-sm text-slate-400 space-y-1">
            <div>• Check for entity overlap with sibling pages</div>
            <div>• Include internal link to Target Page</div>
            <div>• Apply schema markup</div>
            <div>• Queue for your approval before publishing</div>
          </div>
        </div>

        <div className="flex gap-3">
          <button className="btn-secondary flex-1" onClick={onClose}>Cancel</button>
          <button className="btn-primary flex-1 justify-center">
            <Zap size={14} /> Generate Content
          </button>
        </div>
      </div>
    </div>
  )
}
