'use client'

import { useState } from 'react'
import Header from './Header'
import Sidebar from './Sidebar'
import GovernanceDashboard from './screens/GovernanceDashboard'
import SiloPlanner from './screens/SiloPlanner'
import ApprovalQueue from './screens/ApprovalQueue'
import ContentHub from './screens/ContentHub'
import Settings from './screens/Settings'
import GenerateModal from './modals/GenerateModal'
import ApprovalModal from './modals/ApprovalModal'

export type TabType = 'dashboard' | 'silos' | 'approvals' | 'content' | 'links' | 'settings'
export type AutomationMode = 'manual' | 'semi' | 'full'

export interface CannibalizationIssue {
  id: number
  keyword: string
  pages: string[]
  severity: 'high' | 'medium' | 'low'
  impressions: number
  splitClicks: string
  recommendation: string
}

export interface SupportingPage {
  title: string
  url: string
  status: 'published' | 'draft' | 'suggested'
  linked: boolean
  entities: string[]
}

export interface Silo {
  id: number
  name: string
  targetPage: {
    title: string
    url: string
    status: string
    entities: string[]
  }
  supportingPages: SupportingPage[]
}

export interface PendingChange {
  id: number
  type: string
  description: string
  risk: 'safe' | 'destructive'
  impact: string
  doctrine?: string
}

// Sample data
export const cannibalizationIssues: CannibalizationIssue[] = [
  { 
    id: 1, 
    keyword: 'kitchen remodeling', 
    pages: ['/kitchen-remodel-cost', '/kitchen-renovation-guide', '/remodel-your-kitchen'], 
    severity: 'high', 
    impressions: 12400, 
    splitClicks: '34% / 41% / 25%', 
    recommendation: 'Consolidate into single Target Page' 
  },
  { 
    id: 2, 
    keyword: 'bathroom vanity ideas', 
    pages: ['/bathroom-vanity-styles', '/vanity-buying-guide'], 
    severity: 'medium', 
    impressions: 8200, 
    splitClicks: '52% / 48%', 
    recommendation: 'Differentiate entity targeting' 
  },
  { 
    id: 3, 
    keyword: 'hardwood floor installation', 
    pages: ['/hardwood-installation', '/flooring-installation-cost'], 
    severity: 'low', 
    impressions: 3100, 
    splitClicks: '78% / 22%', 
    recommendation: 'Add internal links to strengthen Target' 
  },
]

export const silos: Silo[] = [
  {
    id: 1,
    name: 'Kitchen Remodeling',
    targetPage: { 
      title: 'Complete Kitchen Remodeling Guide', 
      url: '/kitchen-remodel-guide', 
      status: 'published', 
      entities: ['kitchen remodel', 'renovation cost', 'kitchen design'] 
    },
    supportingPages: [
      { title: 'Kitchen Cabinet Styles 2024', url: '/kitchen-cabinets', status: 'published', linked: true, entities: ['cabinet styles', 'shaker cabinets'] },
      { title: 'Countertop Materials Compared', url: '/countertop-materials', status: 'published', linked: true, entities: ['granite', 'quartz', 'marble'] },
      { title: 'Kitchen Layout Ideas', url: '/kitchen-layouts', status: 'draft', linked: false, entities: ['galley kitchen', 'L-shaped'] },
      { title: 'Kitchen Lighting Guide', url: '/kitchen-lighting', status: 'suggested', linked: false, entities: ['pendant lights', 'under-cabinet'] },
    ]
  },
  {
    id: 2,
    name: 'Bathroom Renovation',
    targetPage: { 
      title: 'Bathroom Renovation Planning', 
      url: '/bathroom-renovation', 
      status: 'published', 
      entities: ['bathroom remodel', 'renovation timeline'] 
    },
    supportingPages: [
      { title: 'Bathroom Tile Options', url: '/bathroom-tile', status: 'published', linked: true, entities: ['ceramic tile', 'porcelain'] },
      { title: 'Vanity Selection Guide', url: '/bathroom-vanity', status: 'published', linked: false, entities: ['floating vanity', 'double sink'] },
    ]
  },
]

export const pendingChanges: PendingChange[] = [
  { id: 1, type: 'link_add', description: 'Add internal link from /kitchen-cabinets to /kitchen-remodel-guide', risk: 'safe', impact: 'Strengthens Target Page authority', doctrine: 'LINK_EQUITY_001' },
  { id: 2, type: 'redirect', description: 'Redirect /old-kitchen-page → /kitchen-remodel-guide', risk: 'destructive', impact: 'Consolidates 890 monthly impressions', doctrine: 'CANN_RESTORE_001' },
  { id: 3, type: 'content_generate', description: 'Generate Supporting Page: "Kitchen Lighting Guide"', risk: 'safe', impact: 'Fills entity gap in silo', doctrine: 'ARCH_003' },
  { id: 4, type: 'entity_assign', description: 'Assign entities [pendant lights, task lighting] to /kitchen-lighting', risk: 'safe', impact: 'Improves semantic targeting', doctrine: 'ENTITY_001' },
  { id: 5, type: 'content_merge', description: 'Merge /remodel-your-kitchen into /kitchen-remodel-guide', risk: 'destructive', impact: 'Eliminates cannibalization, consolidates 4,100 impressions', doctrine: 'CANN_RESTORE_002' },
]

export default function Dashboard() {
  const [activeTab, setActiveTab] = useState<TabType>('dashboard')
  const [automationMode, setAutomationMode] = useState<AutomationMode>('manual')
  const [selectedSilo, setSelectedSilo] = useState<Silo | null>(null)
  const [showGenerateModal, setShowGenerateModal] = useState(false)
  const [showApprovalModal, setShowApprovalModal] = useState(false)

  const healthScore = 72

  const renderScreen = () => {
    switch (activeTab) {
      case 'dashboard':
        return (
          <GovernanceDashboard
            healthScore={healthScore}
            cannibalizationIssues={cannibalizationIssues}
            silos={silos}
            pendingChanges={pendingChanges}
            onViewSilo={(silo) => { setSelectedSilo(silo); setActiveTab('silos') }}
            onViewApprovals={() => setActiveTab('approvals')}
            onShowApprovalModal={() => setShowApprovalModal(true)}
          />
        )
      case 'silos':
        return (
          <SiloPlanner
            silos={silos}
            selectedSilo={selectedSilo}
            onGenerateClick={() => setShowGenerateModal(true)}
          />
        )
      case 'approvals':
        return <ApprovalQueue pendingChanges={pendingChanges} />
      case 'content':
        return (
          <ContentHub onGenerateClick={() => setShowGenerateModal(true)} />
        )
      case 'settings':
        return (
          <Settings
            automationMode={automationMode}
            onAutomationChange={setAutomationMode}
          />
        )
      default:
        return null
    }
  }

  return (
    <div className="min-h-screen">
      <Header 
        automationMode={automationMode} 
        onAutomationChange={setAutomationMode} 
      />
      
      <div className="flex">
        <Sidebar 
          activeTab={activeTab} 
          onTabChange={setActiveTab}
          pendingCount={pendingChanges.length}
        />
        
        <main className="flex-1 p-8 max-w-[1200px]">
          {renderScreen()}
        </main>
      </div>

      {showGenerateModal && (
        <GenerateModal 
          silos={silos}
          onClose={() => setShowGenerateModal(false)} 
        />
      )}

      {showApprovalModal && (
        <ApprovalModal onClose={() => setShowApprovalModal(false)} />
      )}
    </div>
  )
}
