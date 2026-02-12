#!/usr/bin/env node
/**
 * Generate WordPress asset file for plugin build
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const distDir = path.join(__dirname, '..', 'react-ui', 'dist');
const assetFile = path.join(distDir, 'index.asset.php');

// Get version from package.json
const packageJson = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'react-ui', 'package.json'), 'utf8'));
const version = packageJson.version;

// Generate asset file content
const assetContent = `<?php return array('dependencies' => array('react', 'react-dom'), 'version' => '${version}');`;

// Ensure dist directory exists
if (!fs.existsSync(distDir)) {
  fs.mkdirSync(distDir, { recursive: true });
}

// Write asset file
fs.writeFileSync(assetFile, assetContent);
console.log(`Generated ${assetFile} with version ${version}`);
