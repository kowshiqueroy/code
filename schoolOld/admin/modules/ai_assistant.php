<?php
/**
 * Admin AI Prompt Generator
 * Generates optimized prompts to copy/paste into external AI tools (ChatGPT, Claude, Gemini).
 */
$admin_title = 'AI Content Assistant';

// Safely get settings (fallback to defaults if functions aren't defined in this snippet)
$site_name    = function_exists('get_setting') ? get_setting('site_name_en', 'Our School') : 'Our School';
$site_name_bn = function_exists('get_setting') ? get_setting('site_name_bn', '') : '';
$inst_type    = function_exists('get_setting') ? get_setting('institute_type', 'school') : 'school';
?>

<div style="max-width:900px; margin: 0 auto;">
  
  <div class="panel">
    <div class="panel-header">
      <div class="panel-title">🤖 AI Prompt Generator</div>
      <span style="font-size:.78rem;color:var(--text-muted);background:var(--bg);padding:4px 10px;border-radius:6px;border:1px solid var(--border)">Step 1: Create Prompt</span>
    </div>
    <div class="panel-body">
      <div class="alert alert-info" style="margin-bottom: 24px;">
        💡 <strong>How it works:</strong> Describe what you need below. We will generate an optimized prompt. You can copy it, paste it into your favorite AI (ChatGPT, Claude, or Gemini), and paste the result back here.
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label" style="display:block; font-weight:600; margin-bottom:8px;">Content Type</label>
          <select id="aiType" class="form-control" onchange="updatePromptHint()">
            <option value="page">📄 General Page Content</option>
            <option value="notice">📋 Official Notice</option>
            <option value="announcement">📢 Announcement</option>
            <option value="principal_message">👤 Principal's Message</option>
            <option value="about">🏫 About Us / History</option>
            <option value="admission">📝 Admission Information</option>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label" style="display:block; font-weight:600; margin-bottom:8px;">Output Language</label>
          <select id="aiLang" class="form-control">
            <option value="en">🇬🇧 English</option>
            <option value="bn">🇧🇩 Bangla (বাংলা)</option>
            <option value="both">🌐 Both Languages</option>
          </select>
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 20px;">
        <label class="form-label" id="promptLabel" style="display:block; font-weight:600; margin-bottom:8px;">Describe what you want</label>
        <textarea id="aiInput" class="form-control" rows="4" placeholder="e.g. Write about the founding of our school in 1975, its achievements, and commitment to quality education..."></textarea>
        <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 6px;" id="promptHint">Be specific about topics, tone, or any details you want included.</div>
      </div>

      <div style="margin-bottom:24px">
        <div style="font-size:.85rem;font-weight:700;color:var(--text-muted);margin-bottom:8px">Quick Starters:</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap" id="quickPrompts"></div>
      </div>

      <button onclick="generatePrompt()" id="generateBtn" class="btn btn-primary">
        ⚡ Generate Optimized Prompt
      </button>
    </div>
  </div>

  <div id="promptSection" class="panel" style="display:none; margin-top: 24px; border-color: var(--primary);">
    <div class="panel-header" style="background: var(--primary-light);">
      <div class="panel-title" style="color: var(--primary-dark);">📋 Your AI Prompt</div>
      <span style="font-size:.78rem;color:var(--primary-dark);background:var(--white);padding:4px 10px;border-radius:6px;border:1px solid var(--primary-light)">Step 2: Copy to AI</span>
    </div>
    <div class="panel-body">
      <p style="font-size: 0.9rem; margin-bottom: 12px;">Click copy, then paste this into <strong><a href="https://chat.openai.com/" target="_blank" style="text-decoration: underline;">ChatGPT</a></strong>, <strong><a href="https://claude.ai/" target="_blank" style="text-decoration: underline;">Claude</a></strong>, or <strong><a href="https://gemini.google.com/" target="_blank" style="text-decoration: underline;">Gemini</a></strong>.</p>
      
      <textarea id="generatedPrompt" class="form-control" rows="8" style="font-family: monospace; font-size: 0.9rem; background: #f8fafc; cursor: text;" readonly></textarea>
      
      <div style="margin-top: 16px;">
        <button onclick="copyPrompt()" class="btn btn-accent">
          📋 Copy Prompt to Clipboard
        </button>
      </div>
    </div>
  </div>

  <div id="previewSection" class="panel" style="display:none; margin-top: 24px;">
    <div class="panel-header">
      <div class="panel-title">👁️ Paste & Preview Result</div>
      <span style="font-size:.78rem;color:var(--text-muted);background:var(--bg);padding:4px 10px;border-radius:6px;border:1px solid var(--border)">Step 3: Render</span>
    </div>
    <div class="panel-body">
      
      <div class="form-group" style="margin-bottom: 20px;">
        <label class="form-label" style="display:block; font-weight:600; margin-bottom:8px;">Paste the AI's HTML response here:</label>
        <textarea id="aiResponse" class="form-control" rows="6" placeholder="Paste the HTML code generated by the AI here..." style="font-family: monospace; font-size: 0.85rem; background: #1a1a2e; color: #a8ff78; border-color: #1a1a2e;"></textarea>
      </div>

      <div style="display:flex; gap: 12px; margin-bottom: 24px;">
        <button onclick="renderPreview()" class="btn btn-outline">
          🔄 Update Preview
        </button>
        <button onclick="useInPage()" class="btn btn-primary">
          📄 Use in New Page
        </button>
      </div>

      <div style="font-size:.8rem;font-weight:700;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:.04em">Live Preview:</div>
      <div id="contentPreview" style="background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:32px; min-height:150px; font-family:'Merriweather Sans', 'Hind Siliguri', sans-serif; line-height:1.8; background-color: #fff;">
        <em style="color: var(--text-muted);">Content preview will appear here...</em>
      </div>

    </div>
  </div>

</div>

<script>
// PHP variables injected into JS safely
const siteData = {
  name: <?= json_encode($site_name) ?>,
  nameBn: <?= json_encode($site_name_bn) ?>,
  type: <?= json_encode($inst_type) ?>
};

const quickPromptData = {
  page: [
    'Write a general about us page including history, mission and vision',
    'Write a curriculum and academic programs section',
    'Write student achievements and co-curricular activities section',
  ],
  notice: [
    'Notice for annual sports day next month',
    'Notice for half-yearly examination schedule',
    'Notice regarding collection of admit cards',
  ],
  announcement: [
    'Congratulate students for excellent SSC results',
    'Welcome new students at the beginning of academic year',
    'Announce parent-teacher meeting next week',
  ],
  principal_message: [
    'Write a warm and inspiring message emphasizing discipline and quality education',
    'Write a message welcoming new academic year with focus on digital literacy',
  ],
  about: [
    'Write about a school founded in 1975 serving rural Bangladesh with 1200 students',
    'Write about a girls college established in 1990 with focus on women empowerment',
  ],
  admission: [
    'Write admission rules for Class VI with entrance test requirements',
    'Write HSC admission information with merit-based selection process',
  ]
};

function updatePromptHint() {
  const type = document.getElementById('aiType').value;
  const hints = {
    page: 'Describe the page topic, key points to cover, and any specific information about your institution.',
    notice: 'Describe the notice subject, date, and any specific instructions.',
    announcement: 'Describe the occasion and what you want to communicate.',
    principal_message: 'Describe the theme, values, or occasion for the message.',
    about: 'Include founding year, location, student count, achievements, notable alumni.',
    admission: 'Specify class/grade, eligibility, process details, important dates.',
  };
  document.getElementById('promptHint').textContent = hints[type] || hints.page;

  const prompts = document.getElementById('quickPrompts');
  prompts.innerHTML = '';
  (quickPromptData[type] || []).forEach(p => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-sm btn-outline'; // Changed to match your new modern styling
    btn.style.fontSize = '0.75rem';
    btn.style.padding = '4px 10px';
    btn.textContent = p.substring(0, 45) + (p.length > 45 ? '…' : '');
    btn.title = p;
    btn.onclick = () => { document.getElementById('aiInput').value = p; };
    prompts.appendChild(btn);
  });
}

// Initialize hints on load
updatePromptHint();

function generatePrompt() {
  const type = document.getElementById('aiType').value;
  const input = document.getElementById('aiInput').value.trim();
  const lang = document.getElementById('aiLang').value;

  if (!input) { 
    alert('Please describe what content you want to generate.'); 
    document.getElementById('aiInput').focus();
    return; 
  }

  // Define system instructions based on type
  const systemPrompts = {
    page: `You are a professional content writer for ${siteData.name}, a Bangladeshi ${siteData.type}. Write clean, formal, government-compliant educational content in HTML. Use proper heading tags (h2, h3), paragraphs, and lists where appropriate. Keep the tone formal and respectful.`,
    
    notice: `You are writing official notices for ${siteData.name} ${siteData.nameBn ? '('+siteData.nameBn+')' : ''}, a Bangladeshi educational institution. Write a formal, clear official notice. Include: date reference if needed, clear subject, body, and closing. Format as clean HTML. Keep it brief and official.`,
    
    announcement: `You are writing school announcements for ${siteData.name}. Write warm, encouraging announcements suitable for students, parents, and guardians. Format as clean HTML.`,
    
    principal_message: `You are writing a Principal's Message for ${siteData.name}. Write an inspiring, educational, formal message from the principal. Include: welcome, institution's vision, commitment to students, encouragement. Format as clean HTML with proper paragraphs.`,
    
    about: `You are writing the About Us / History section for ${siteData.name}. Include: founding history, mission, vision, achievements, and commitment to education. Write in a formal, proud tone. Format as clean HTML.`,
    
    admission: `You are writing admission information for ${siteData.name}. Include: eligibility criteria, required documents, admission process, important dates (use placeholders if unknown), fees structure. Format clearly with headings, bold text, and lists. Format as clean HTML.`
  };

  let promptBuilder = "SYSTEM INSTRUCTIONS:\n" + (systemPrompts[type] || systemPrompts['page']);
  promptBuilder += "\n\nSTRICT FORMATTING RULE:\nReturn ONLY the raw HTML code. Do NOT wrap the response in markdown blocks (like ```html). Do not include any introductory or concluding conversational text.";

  // Language instructions
  if (lang === 'bn') {
    promptBuilder += "\n\nLANGUAGE RULE:\nIMPORTANT: Write the content entirely in standard written Bangla (বাংলা).";
  } else if (lang === 'en') {
    promptBuilder += "\n\nLANGUAGE RULE:\nIMPORTANT: Write the content entirely in formal English.";
  } else if (lang === 'both') {
    promptBuilder += "\n\nLANGUAGE RULE:\nIMPORTANT: Provide the content in BOTH English and Bangla (বাংলা). Provide the English version first, followed by a visual separator (like <hr>), and then the Bangla version.";
  }

  promptBuilder += "\n\n-------------------------\nUSER REQUEST:\n" + input;

  // Display the prompt
  document.getElementById('generatedPrompt').value = promptBuilder;
  
  // Show the Prompt and Preview sections
  document.getElementById('promptSection').style.display = 'block';
  document.getElementById('previewSection').style.display = 'block';
  
  // Scroll to prompt section smoothly
  document.getElementById('promptSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function copyPrompt() {
  const ta = document.getElementById('generatedPrompt');
  ta.select();
  document.execCommand('copy');
  
  // Feedback
  const btn = event.currentTarget;
  const originalText = btn.innerHTML;
  btn.innerHTML = '✅ Copied! Now paste into ChatGPT/Claude';
  btn.classList.add('btn-primary');
  btn.classList.remove('btn-accent');
  
  setTimeout(() => {
    btn.innerHTML = originalText;
    btn.classList.remove('btn-primary');
    btn.classList.add('btn-accent');
  }, 3000);
}

function renderPreview() {
  let rawHtml = document.getElementById('aiResponse').value.trim();
  
  if (!rawHtml) {
    alert("Please paste the AI's HTML response into the text area first.");
    return;
  }
  
  // Clean up potential markdown if the AI ignored the strict formatting rule
  rawHtml = rawHtml.replace(/^```html?\s*/im, '');
  rawHtml = rawHtml.replace(/```\s*$/im, '');
  
  document.getElementById('contentPreview').innerHTML = rawHtml;
}

function useInPage() {
  let content = document.getElementById('aiResponse').value.trim();
  
  if (!content) {
    alert("Please paste the AI's HTML response into the text area first.");
    return;
  }
  
  // Clean up potential markdown
  content = content.replace(/^```html?\s*/im, '');
  content = content.replace(/```\s*$/im, '');
  
  const encoded = encodeURIComponent(content);
  // Opens the add page screen, passing the HTML via URL parameter
  window.open('/admin/?action=pages&mode=add&prefill=' + encoded, '_blank');
}
</script>