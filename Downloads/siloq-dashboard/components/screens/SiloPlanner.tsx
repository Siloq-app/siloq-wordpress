'use client'

import { Plus, Eye, Link2, CheckCircle, Zap, FileText } from 'lucide-react'
import { Silo } from '../Dashboard'

interface Props {
  silos: Silo[]
  selectedSilo: Silo | null
  onGenerateClick: () => void
}

export default function SiloPlanner({ silos, selectedSilo, onGenerateClick }: Props) {
  const displaySilos = selectedSilo ? [selectedSilo] : silos

  return (
    <div className="card p-7">
      <div className="flex items-center justify-between mb-8">
        <h2 className="text-2xl font-bold">
          {selectedSilo ? selectedSilo.name : 'All Silos'}
        </h2>
        <button className="btn-primary" onClick={onGenerateClick}>
          <Plus size={14} /> Generate Supporting Page
        </button>
      </div>

      {displaySilos.map((silo) => (
        <div key={silo.id} className="mb-8">
          {/* Target Page (King) */}
          <div className="bg-gradient-to-r from-amber-500/10 to-orange-500/10 border border-amber-500/20 rounded-xl p-6 mb-4">
            <div className="flex items-center gap-4">
              <div className="w-12 h-12 bg-gradient-to-br from-amber-500 to-orange-500 rounded-xl flex items-center justify-center text-xl">
                👑
              </div>
              <div className="flex-1">
                <div className="flex items-center gap-2 mb-1">
                  <span className="text-[11px] text-amber-400 uppercase font-semibold">Target Page (King)</span>
                </div>
                <h3 className="text-lg font-semibold mb-1">{silo.targetPage.title}</h3>
                <span className="text-sm text-slate-400 font-mono">{silo.targetPage.url}</span>
                <div className="mt-2">
                  {silo.targetPage.entities.map((e, i) => (
                    <span key={i} className="entity-tag">{e}</span>
                  ))}
                </div>
              </div>
              <div className="flex gap-2">
                <button className="btn-secondary">
                  <Eye size={14} /> View
                </button>
              </div>
            </div>
          </div>

          {/* Supporting Pages (Soldiers) */}
          <div className="pl-6 border-l-2 border-indigo-500/30">
            <div className="text-[11px] text-slate-500 uppercase font-semibold mb-3 ml-2">
              Supporting Pages (Soldiers) — Link UP to Target
            </div>
            
            {silo.supportingPages.map((page, i) => (
              <div
                key={i}
                className="bg-slate-900/40 rounded-xl p-4 mb-3 border border-slate-700/30 flex items-center justify-between"
              >
                <div className="flex items-center gap-4">
                  <div className={`w-8 h-8 rounded-lg flex items-center justify-center text-sm ${
                    page.status === 'published' 
                      ? 'bg-emerald-500/10' 
                      : page.status === 'suggested' 
                        ? 'bg-amber-500/10' 
                        : 'bg-slate-700/50'
                  }`}>
                    ⚔️
                  </div>
                  <div>
                    <div className="text-sm font-medium mb-0.5">{page.title}</div>
                    <div className="text-xs text-slate-500 font-mono">{page.url}</div>
                    <div className="mt-1.5">
                      {page.entities.map((e, j) => (
                        <span key={j} className="entity-tag">{e}</span>
                      ))}
                    </div>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  {page.linked ? (
                    <span className="text-[11px] text-emerald-400 flex items-center gap-1">
                      <Link2 size={12} /> Linked to Target
                    </span>
                  ) : (
                    <button className="btn-secondary text-xs py-1.5 px-3">
                      <Link2 size={12} /> Add Link
                    </button>
                  )}
                  <span className={`text-[10px] px-2 py-1 rounded font-semibold uppercase ${
                    page.status === 'published' 
                      ? 'bg-emerald-500/10 text-emerald-400' 
                      : page.status === 'suggested' 
                        ? 'bg-amber-500/10 text-amber-400' 
                        : 'bg-slate-700/50 text-slate-400'
                  }`}>
                    {page.status}
                  </span>
                </div>
              </div>
            ))}

            {/* Add Supporting Page CTA */}
            <div
              onClick={onGenerateClick}
              className="border-2 border-dashed border-indigo-500/30 rounded-xl p-5 text-center cursor-pointer hover:border-indigo-500/50 hover:bg-indigo-500/5 transition-all"
            >
              <Plus size={20} className="mx-auto mb-2 text-indigo-400" />
              <div className="text-sm text-indigo-300 font-medium">Generate New Supporting Page</div>
              <div className="text-xs text-slate-500">Siloq will create content with proper entity targeting and links</div>
            </div>
          </div>
        </div>
      ))}
    </div>
  )
}
