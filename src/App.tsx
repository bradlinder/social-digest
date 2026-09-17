import { useState } from 'react';
import { 
  GitBranch, CheckCircle2, ShieldCheck, 
  ExternalLink, Copy, Check, Terminal,
  Package, Sparkles, FileCode, Download,
  Tag, History, AlertCircle
} from 'lucide-react';
import JSZip from 'jszip';
import { PLUGIN_META, CHANGELOG_DATA } from './changelogData';
import socialDigestCode from '../social-digest.php?raw';
import readmeTextCode from '../readme.txt?raw';

export default function App() {
  const [copiedZip, setCopiedZip] = useState(false);
  const [copiedRollback, setCopiedRollback] = useState(false);
  const [isDownloading, setIsDownloading] = useState(false);
  const [filterQuery, setFilterQuery] = useState('');

  const downloadPluginZip = async () => {
    try {
      setIsDownloading(true);
      const zip = new JSZip();
      
      // Standard WordPress plugin zip folder hierarchy:
      // social-digest/
      //   ├── social-digest.php
      //   └── readme.txt
      const pluginFolder = zip.folder('social-digest');
      if (pluginFolder) {
        pluginFolder.file('social-digest.php', socialDigestCode);
        pluginFolder.file('readme.txt', readmeTextCode);
      }

      const content = await zip.generateAsync({ type: 'blob' });
      const url = URL.createObjectURL(content);
      const link = document.createElement('a');
      link.href = url;
      link.download = `social-digest-v${PLUGIN_META.version}.zip`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
    } catch (err) {
      console.error('Failed to generate zip:', err);
    } finally {
      setIsDownloading(false);
    }
  };

  const copyToClipboard = (text: string, type: 'zip' | 'rollback') => {
    navigator.clipboard.writeText(text);
    if (type === 'zip') {
      setCopiedZip(true);
      setTimeout(() => setCopiedZip(false), 2000);
    } else {
      setCopiedRollback(true);
      setTimeout(() => setCopiedRollback(false), 2000);
    }
  };

  const filteredReleases = CHANGELOG_DATA.filter((rel) => 
    rel.version.toLowerCase().includes(filterQuery.toLowerCase()) ||
    rel.highlights.some(h => h.toLowerCase().includes(filterQuery.toLowerCase()))
  );

  return (
    <div className="min-h-screen bg-slate-900 text-slate-100 flex flex-col font-sans selection:bg-blue-500 selection:text-white">
      {/* Top Header Bar */}
      <header className="border-b border-slate-800 bg-slate-950/80 backdrop-blur sticky top-0 z-30 px-6 py-4 flex items-center justify-between">
        <div className="flex items-center space-x-3">
          <div className="w-9 h-9 rounded-lg bg-blue-600/20 border border-blue-500/30 flex items-center justify-center text-blue-400">
            <Package className="w-5 h-5" />
          </div>
          <div>
            <div className="flex items-center space-x-2">
              <h1 className="text-base font-bold tracking-tight text-white">{PLUGIN_META.name}</h1>
              <span className="bg-blue-500/20 border border-blue-500/40 text-blue-400 text-xs px-2 py-0.5 rounded-full font-mono font-semibold">
                v{PLUGIN_META.version}
              </span>
            </div>
            <p className="text-xs text-slate-400">Official WordPress Plugin Release &amp; Changelog Hub</p>
          </div>
        </div>

        <div className="flex items-center space-x-3 text-xs">
          <button
            onClick={downloadPluginZip}
            disabled={isDownloading}
            className="flex items-center space-x-1.5 bg-blue-600 hover:bg-blue-500 text-white font-medium px-3.5 py-1.5 rounded-md shadow-sm transition-colors cursor-pointer"
          >
            <Download className="w-3.5 h-3.5" />
            <span>{isDownloading ? 'Packaging...' : `Download v${PLUGIN_META.version} (.zip)`}</span>
          </button>
          <div className="hidden sm:flex items-center space-x-1.5 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-3 py-1.5 rounded-md">
            <CheckCircle2 className="w-3.5 h-3.5" />
            <span>Plugin Healthy</span>
          </div>
        </div>
      </header>

      {/* Main Content Area */}
      <main className="flex-1 max-w-5xl w-full mx-auto p-6 md:p-8 space-y-8">
        {/* Release Status & Fast Actions Grid */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {/* Card 1: Active Production Release */}
          <div className="bg-slate-800/60 border border-slate-700/70 rounded-xl p-5 shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div className="absolute top-0 right-0 w-24 h-24 bg-blue-500/5 rounded-bl-full pointer-events-none" />
            <div>
              <div className="text-xs uppercase tracking-wider text-slate-400 font-semibold mb-1 flex items-center space-x-1.5">
                <Tag className="w-3.5 h-3.5 text-blue-400" />
                <span>Current Release</span>
              </div>
              <div className="text-2xl font-bold text-white font-mono flex items-center space-x-2 mt-1">
                <span>v{PLUGIN_META.version}</span>
                <span className="text-xs bg-blue-500/20 text-blue-300 border border-blue-500/30 px-2 py-0.5 rounded font-sans font-normal">
                  Production Ready
                </span>
              </div>
              <div className="mt-3 text-xs text-slate-400 space-y-1">
                <div>WordPress: <strong className="text-slate-200">{PLUGIN_META.requiresWP}</strong> (tested up to {PLUGIN_META.testedUpTo})</div>
                <div>PHP: <strong className="text-slate-200">{PLUGIN_META.requiresPHP}</strong></div>
                <div>License: <strong className="text-slate-200">{PLUGIN_META.license}</strong></div>
              </div>
            </div>
            <div className="mt-4 pt-3 border-t border-slate-700/60 flex items-center justify-between text-xs">
              <button
                onClick={downloadPluginZip}
                disabled={isDownloading}
                className="text-xs bg-blue-600/30 hover:bg-blue-600/50 border border-blue-500/40 text-blue-300 hover:text-white px-3 py-1.5 rounded-lg font-medium flex items-center space-x-1.5 transition-colors"
              >
                <Download className="w-3.5 h-3.5" />
                <span>Download Plugin (.zip)</span>
              </button>
              <span className="flex items-center space-x-1 text-emerald-400 font-medium">
                <CheckCircle2 className="w-3.5 h-3.5" />
                <span>Verified</span>
              </span>
            </div>
          </div>

          {/* Card 2: Rollback & Snapshot Safety */}
          <div className="bg-slate-800/60 border border-slate-700/70 rounded-xl p-5 shadow-sm flex flex-col justify-between">
            <div>
              <div className="text-xs uppercase tracking-wider text-slate-400 font-semibold mb-1 flex items-center space-x-1.5">
                <ShieldCheck className="w-3.5 h-3.5 text-emerald-400" />
                <span>Rollback Safety Guard</span>
              </div>
              <div className="text-sm font-semibold text-white mt-1">
                Durable Backup Checkpoint
              </div>
              <p className="text-xs text-slate-400 mt-1.5 leading-relaxed">
                Snapshot archived at <code className="text-slate-300 bg-slate-900 px-1 py-0.5 rounded font-mono">.backups/{PLUGIN_META.rollbackTarget}</code> and tagged as <strong className="text-emerald-400 font-mono">beta_latest</strong> in Git.
              </p>
            </div>

            <div className="mt-4 pt-3 border-t border-slate-700/60">
              <button
                onClick={() => copyToClipboard(`git checkout ${PLUGIN_META.rollbackTarget}`, 'rollback')}
                className="w-full text-xs bg-slate-900 hover:bg-slate-950 border border-slate-700 text-slate-300 hover:text-white px-3 py-2 rounded-lg font-mono flex items-center justify-between transition-colors"
                title="Click to copy rollback command"
              >
                <div className="flex items-center space-x-2 truncate">
                  <Terminal className="w-3.5 h-3.5 text-slate-500" />
                  <span className="truncate">git checkout {PLUGIN_META.rollbackTarget}</span>
                </div>
                {copiedRollback ? <Check className="w-3.5 h-3.5 text-emerald-400 flex-shrink-0" /> : <Copy className="w-3.5 h-3.5 text-slate-400 flex-shrink-0" />}
              </button>
            </div>
          </div>

          {/* Card 3: Release Archive & Cleanliness */}
          <div className="bg-slate-800/60 border border-slate-700/70 rounded-xl p-5 shadow-sm flex flex-col justify-between">
            <div>
              <div className="text-xs uppercase tracking-wider text-slate-400 font-semibold mb-1 flex items-center space-x-1.5">
                <Sparkles className="w-3.5 h-3.5 text-amber-400" />
                <span>Clean Distribution</span>
              </div>
              <div className="text-sm font-semibold text-white mt-1">
                WordPress-Compliant Structure
              </div>
              <p className="text-xs text-slate-400 mt-1.5 leading-relaxed">
                Packaged inside a root <code className="text-slate-300 bg-slate-900 px-1 py-0.5 rounded font-mono">social-digest/</code> folder containing <code className="text-slate-300 bg-slate-900 px-1 py-0.5 rounded font-mono">social-digest.php</code> and <code className="text-slate-300 bg-slate-900 px-1 py-0.5 rounded font-mono">readme.txt</code>.
              </p>
            </div>

            <div className="mt-4 pt-3 border-t border-slate-700/60 flex items-center justify-between">
              <button
                onClick={() => copyToClipboard('mkdir -p social-digest && cp social-digest.php readme.txt social-digest/ && zip -r social-digest.zip social-digest/', 'zip')}
                className="text-xs text-blue-400 hover:text-blue-300 font-medium flex items-center space-x-1.5 transition-colors"
              >
                {copiedZip ? <Check className="w-3.5 h-3.5 text-emerald-400" /> : <Copy className="w-3.5 h-3.5" />}
                <span>{copiedZip ? 'Command copied!' : 'Copy terminal packaging cmd'}</span>
              </button>
              <a
                href={`https://github.com/${PLUGIN_META.githubRepo}`}
                target="_blank"
                rel="noopener noreferrer"
                className="text-xs text-slate-400 hover:text-slate-200 flex items-center space-x-1"
              >
                <span>GitHub</span>
                <ExternalLink className="w-3 h-3" />
              </a>
            </div>
          </div>
        </div>

        {/* Installation Tip Banner */}
        <div className="bg-blue-950/40 border border-blue-800/60 rounded-xl p-4 text-xs text-blue-200 flex items-start space-x-3">
          <AlertCircle className="w-4 h-4 text-blue-400 flex-shrink-0 mt-0.5" />
          <div className="leading-relaxed">
            <strong className="text-white font-semibold">How to Install in WordPress:</strong> Click the <strong>Download v{PLUGIN_META.version} (.zip)</strong> button above. Then in your WordPress admin, navigate to <strong>Plugins → Add New Plugin → Upload Plugin</strong>, choose the downloaded <code className="bg-blue-900/50 text-blue-300 px-1.5 py-0.5 rounded font-mono">social-digest-v{PLUGIN_META.version}.zip</code>, and click <strong>Install Now</strong>. <em>(Do not upload the full repository / AI Studio project ZIP, as WordPress requires the dedicated plugin archive structure)</em>.
          </div>
        </div>

        {/* Live Visual Card Design Preview (v5.6.2) */}
        <section className="bg-slate-800/50 border border-slate-700/70 rounded-xl p-5 sm:p-6 space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-700/60">
            <div>
              <div className="flex items-center space-x-2">
                <Sparkles className="w-4 h-4 text-blue-400" />
                <h2 className="text-sm font-bold text-white uppercase tracking-wider">
                  Native Post Card Design Preview (v5.6.2)
                </h2>
                <span className="text-[10px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 px-2 py-0.5 rounded-full font-mono">
                  Live Style
                </span>
              </div>
              <p className="text-xs text-slate-400 mt-1">
                Visual demonstration of the newly updated card header layout with inline follow links and calendar-free footer.
              </p>
            </div>
          </div>

          {/* Rendered WordPress Native Card Simulation */}
          <div className="bg-slate-100 rounded-lg p-4 sm:p-6 flex justify-center">
            <div
              className="social-post social-card w-full max-w-[600px] bg-white text-slate-800 rounded-xl p-4 sm:p-5 shadow-sm border border-slate-200 border-l-4 border-l-[#0284c7] font-sans"
              style={{ margin: '0 auto' }}
            >
              {/* Card Header */}
              <div className="flex items-center gap-3 pb-3 mb-3 border-b border-slate-100">
                <div className="w-11 h-11 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold text-sm shadow-inner flex-shrink-0">
                  BL
                </div>
                <div className="min-w-0 flex-1 leading-snug">
                  <div className="font-bold text-[15px] text-slate-900 truncate">
                    Liliputing
                  </div>
                  <div className="text-[13px] text-slate-500 flex flex-wrap items-center gap-x-2 gap-y-0.5 mt-0.5">
                    <span className="inline-flex items-center whitespace-nowrap">
                      <a href="https://bsky.app/profile/liliputing.bsky.social" target="_blank" rel="noopener noreferrer" className="text-slate-500 hover:text-slate-700 hover:underline">
                        @liliputing.bsky.social
                      </a>
                      <span className="text-slate-400 mx-1.5">·</span>
                      <a href="https://bsky.app/profile/liliputing.bsky.social" target="_blank" rel="noopener noreferrer" className="text-[#0284c7] font-semibold hover:underline">
                        Follow
                      </a>
                    </span>
                    <span className="text-slate-300">·</span>
                    <span className="inline-flex items-center whitespace-nowrap">
                      <a href="https://fosstodon.org/@bradlinder" target="_blank" rel="noopener noreferrer" className="text-slate-500 hover:text-slate-700 hover:underline">
                        @bradlinder@fosstodon.org
                      </a>
                      <span className="text-slate-400 mx-1.5">·</span>
                      <a href="https://fosstodon.org/@bradlinder" target="_blank" rel="noopener noreferrer" className="text-[#7e22ce] font-semibold hover:underline">
                        Follow
                      </a>
                    </span>
                  </div>
                </div>
              </div>

              {/* Card Body */}
              <div className="text-[15px] leading-relaxed text-slate-800 mb-3 break-words">
                A look at the refreshed Social Digest post card formatting. Handles and follow links are now paired directly inline, closely resembling the native Bluesky embed design.
              </div>

              {/* Card Footer (Clean Date + Platform Badges) */}
              <div className="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-slate-100 text-[13px] text-slate-500">
                <div className="flex items-center">
                  <span>Oct 14, 2026 · 3:45 PM</span>
                </div>
                <div className="flex items-center gap-1.5 flex-wrap">
                  <a
                    href="https://bsky.app"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded bg-sky-50 text-[#0284c7] border border-sky-200 hover:bg-sky-100 transition-colors"
                  >
                    <span>🦋 Bluesky</span>
                    <span className="text-[11px] bg-blue-100 text-blue-800 px-1.5 py-0.2 rounded-full">❤️ 42</span>
                    <span className="text-[10px]">↗</span>
                  </a>
                  <a
                    href="https://fosstodon.org"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded bg-purple-50 text-[#7e22ce] border border-purple-200 hover:bg-purple-100 transition-colors"
                  >
                    <span>🐘 Mastodon</span>
                    <span className="text-[11px] bg-purple-100 text-purple-800 px-1.5 py-0.2 rounded-full">⭐ 18 · 🔁 5</span>
                    <span className="text-[10px]">↗</span>
                  </a>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* Changelog Section */}
        <section className="space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-3">
            <div className="flex items-center space-x-2">
              <History className="w-5 h-5 text-blue-400" />
              <h2 className="text-lg font-bold text-white tracking-tight">Plugin Changelog &amp; Release History</h2>
              <span className="text-xs bg-slate-800 text-slate-400 px-2 py-0.5 rounded-full font-mono">
                {CHANGELOG_DATA.length} versions
              </span>
            </div>

            <div className="relative">
              <input
                type="text"
                placeholder="Search releases or changes..."
                value={filterQuery}
                onChange={(e) => setFilterQuery(e.target.value)}
                className="w-full sm:w-64 text-xs bg-slate-950 border border-slate-800 focus:border-blue-500 rounded-lg px-3 py-1.5 text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
            </div>
          </div>

          <div className="space-y-4">
            {filteredReleases.map((entry) => (
              <div 
                key={entry.version}
                className={`bg-slate-800/40 border rounded-xl p-5 transition-colors ${
                  entry.isLatest 
                    ? 'border-blue-500/40 bg-slate-800/70 shadow-lg shadow-blue-500/5' 
                    : entry.isBeta 
                      ? 'border-amber-500/30' 
                      : 'border-slate-800'
                }`}
              >
                <div className="flex items-center justify-between mb-3">
                  <div className="flex items-center space-x-2.5">
                    <span className="text-base font-bold font-mono text-white">
                      v{entry.version}
                    </span>
                    {entry.isLatest && (
                      <span className="bg-blue-600 text-white text-[11px] font-semibold px-2 py-0.5 rounded-full">
                        Current Active
                      </span>
                    )}
                    {entry.isBeta && (
                      <span className="bg-amber-500/20 text-amber-300 border border-amber-500/30 text-[11px] font-semibold px-2 py-0.5 rounded-full">
                        Rollback Point
                      </span>
                    )}
                  </div>
                  {entry.tag && (
                    <span className="text-xs text-slate-400 font-mono flex items-center space-x-1">
                      <GitBranch className="w-3 h-3 text-slate-500" />
                      <span>{entry.tag}</span>
                    </span>
                  )}
                </div>

                <ul className="space-y-2 text-xs text-slate-300">
                  {entry.highlights.map((item, idx) => (
                    <li key={idx} className="flex items-start space-x-2">
                      <span className="text-blue-400 font-bold mt-0.5">•</span>
                      <span className="leading-relaxed">{item}</span>
                    </li>
                  ))}
                </ul>
              </div>
            ))}

            {filteredReleases.length === 0 && (
              <div className="text-center py-8 text-xs text-slate-500">
                No release matches found for "{filterQuery}".
              </div>
            )}
          </div>
        </section>

        {/* Developer Guidelines & Workflow Note */}
        <div className="bg-slate-950/70 border border-slate-800/80 rounded-xl p-4 text-xs text-slate-400 flex items-start space-x-3">
          <FileCode className="w-4 h-4 text-slate-500 flex-shrink-0 mt-0.5" />
          <div className="leading-relaxed">
            <strong className="text-slate-300">Token-Efficient Development Mode:</strong> The preview now serves as a high-speed Release &amp; Changelog Hub. Code changes and fixes are applied directly to <code className="text-slate-300">social-digest.php</code> and synchronized with <code className="text-slate-300">readme.txt</code>, saving thousands of tokens per conversation turn while keeping release tracking seamless.
          </div>
        </div>
      </main>

      {/* Footer */}
      <footer className="border-t border-slate-800/80 px-6 py-4 text-xs text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-2 bg-slate-950">
        <div>
          <span>{PLUGIN_META.name}</span> &bull; <span>GPLv2 Licensed</span>
        </div>
        <div>
          <span>Crafted for WordPress 6.0+ &bull; Bluesky AT Protocol &bull; Mastodon ActivityPub</span>
        </div>
      </footer>
    </div>
  );
}
