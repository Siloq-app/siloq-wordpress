// Types for Siloq Connector

export interface Page {
  id: number;
  title: string;
  url: string;
  type: string;
  status: string;
  author: string;
  modified: string;
  synced: boolean;
  syncedAt?: string;
}

export interface Config {
  apiKey: string;
  siteId: string;
  connected: boolean;
  autoSync: boolean;
  wizardCompleted: boolean;
}

export interface ScanResult {
  score: number;
  leadGenScore: number;
  issues: Array<{
    type: 'error' | 'warning' | 'info';
    message: string;
  }>;
  recommendations: string[];
}

export enum AppView {
  DASHBOARD = 'dashboard',
  WIZARD = 'wizard',
  SYNC = 'sync',
  SCANNER = 'scanner',
  SETTINGS = 'settings',
}

export interface SyncActivity {
  id: number;
  title: string;
  date: string;
  status: 'success' | 'error';
}
