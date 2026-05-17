/* ========================================
   🌸 S H E N G _ B O T  前 端 交 互 🌸
   No Refresh, All Fun! ✨
   ======================================== */

class ShengBotApp {
  constructor() {
    this.currentPage = 'dashboard';
    this.init();
  }

  init() {
    this.createPetals();
    this.bindEvents();
    this.setupNavigation();
    this.showToast('success', '欢迎回来~ 💕', 'Sheng_Bot 已准备好为您服务!');
  }

  // ================== 🌸 Floating Sakura Petals ==================
  createPetals() {
    const container = document.createElement('div');
    container.className = 'petals-container';
    
    for (let i = 0; i < 20; i++) {
      const petal = document.createElement('div');
      petal.className = 'petal';
      petal.style.left = `${Math.random() * 100}%`;
      petal.style.animationDelay = `${Math.random() * 15}s`;
      petal.style.animationDuration = `${12 + Math.random() * 10}s`;
      container.appendChild(petal);
    }
    
    document.body.insertBefore(container, document.body.firstChild);
  }

  // ================== 🎯 Event Binding ==================
  bindEvents() {
    // Handle form submissions without refresh
    document.addEventListener('submit', (e) => {
      const form = e.target;
      if (form.matches('.cute-form')) {
        e.preventDefault();
        this.handleFormSubmit(form);
      }
    });

    // Event delegation: handle all click events centrally
    document.addEventListener('click', (e) => {
      // Navigation links
      const navLink = e.target.closest('.cute-nav-link');
      if (navLink) {
        e.preventDefault();
        const page = navLink.dataset.page;
        if (page) {
          this.navigateTo(page);
        }
        return;
      }
      
      // Delete buttons
      const deleteBtn = e.target.closest('.delete-btn');
      if (deleteBtn) {
        e.preventDefault();
        this.handleDelete(deleteBtn);
        return;
      }
      
      // Copy buttons
      const copyBtn = e.target.closest('.copy-btn');
      if (copyBtn) {
        this.handleCopy(copyBtn);
        return;
      }
    });
  }

  // ================== 🧭 Navigation ==================
  setupNavigation() {
    // No need for individual listeners - using event delegation now!
    // This ensures dynamic content will work without re-binding
  }

  async navigateTo(page) {
    this.currentPage = page;
    
    // Update active nav
    document.querySelectorAll('.cute-nav-link').forEach(link => {
      link.classList.remove('active');
      if (link.dataset.page === page) {
        link.classList.add('active');
      }
    });

    // Simulate loading
    this.showLoading(true);
    
    // Fake AJAX delay for cuteness
    await this.delay(600);
    
    // Load page content (in real app, this would fetch data)
    this.updatePageContent(page);
    this.showLoading(false);
    
    // Sparkles effect
    this.createSparkles();
  }

  updatePageContent(page) {
    const mainContent = document.querySelector('.cute-main');
    if (!mainContent) return;

    const pages = {
      dashboard: this.getDashboardHTML(),
      qqBots: this.getQQBotsHTML(),
      napcatBots: this.getNapcatBotsHTML(),
      logs: this.getLogsHTML(),
      system: this.getSystemHTML(),
      settings: this.getSettingsHTML()
    };

    mainContent.innerHTML = pages[page] || pages.dashboard;
    
    // Re-bind events for new content
    this.setupNavigation();
  }

  // ================== 📄 Page Templates ==================
  getDashboardHTML() {
    return `
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-value">3</div>
          <div class="stat-label">🤖 QQ 机器人</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">2</div>
          <div class="stat-label">💻 NapCat 机器人</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">1,234</div>
          <div class="stat-label">📨 消息日志</div>
        </div>
      </div>
      
      <div class="cute-card">
        <div class="card-header">
          <h3 class="card-title">✨ 快速操作</h3>
        </div>
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
          <button class="cute-btn cute-btn-primary" onclick="app.showToast('info', '功能开发中~', '敬请期待!')">
            🚀 添加机器人
          </button>
          <button class="cute-btn cute-btn-success" onclick="app.showToast('success', '刷新成功!', '数据已更新')">
            🔄 刷新数据
          </button>
          <button class="cute-btn cute-btn-primary" onclick="app.createSparkles()">
            ✨ 撒花
          </button>
        </div>
      </div>
      
      <div class="cute-card">
        <div class="card-header">
          <h3 class="card-title">📊 系统信息</h3>
        </div>
        <table class="cute-table">
          <thead>
            <tr>
              <th>项目</th>
              <th>状态</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>🖥️ PHP 版本</td>
              <td><span class="cute-badge cute-badge-primary">8.2.0</span></td>
            </tr>
            <tr>
              <td>⚡ Swoole 版本</td>
              <td><span class="cute-badge cute-badge-success">6.2.1</span></td>
            </tr>
            <tr>
              <td>💾 数据库</td>
              <td><span class="cute-badge cute-badge-success">正常</span></td>
            </tr>
            <tr>
              <td>⏰ 服务器时间</td>
              <td><span class="cute-badge cute-badge-info">${new Date().toLocaleString('zh-CN')}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    `;
  }

  // Recreate nav links in dynamic content
  getNavigationHTML() {
    return `
      <aside class="cute-sidebar">
        <div class="sidebar-title">
          <h3>🌸 功能菜单</h3>
        </div>
        <nav>
          <ul class="cute-nav">
            <li>
              <a href="javascript:void(0)" class="cute-nav-link ${this.currentPage === 'dashboard' ? 'active' : ''}" data-page="dashboard">
                <span class="nav-icon">🏠</span>
                仪表板
              </a>
            </li>
            <li>
              <a href="javascript:void(0)" class="cute-nav-link ${this.currentPage === 'qqBots' ? 'active' : ''}" data-page="qqBots">
                <span class="nav-icon">🤖</span>
                官方QQ机器人
              </a>
            </li>
            <li>
              <a href="javascript:void(0)" class="cute-nav-link ${this.currentPage === 'napcatBots' ? 'active' : ''}" data-page="napcatBots">
                <span class="nav-icon">💻</span>
                NapCat机器人
              </a>
            </li>
            <li>
              <a href="javascript:void(0)" class="cute-nav-link ${this.currentPage === 'logs' ? 'active' : ''}" data-page="logs">
                <span class="nav-icon">📨</span>
                消息日志
              </a>
            </li>
            <li>
              <a href="javascript:void(0)" class="cute-nav-link ${this.currentPage === 'system' ? 'active' : ''}" data-page="system">
                <span class="nav-icon">📋</span>
                系统日志
              </a>
            </li>
            <li>
              <a href="javascript:void(0)" class="cute-nav-link ${this.currentPage === 'settings' ? 'active' : ''}" data-page="settings">
                <span class="nav-icon">⚙️</span>
                系统设置
              </a>
            </li>
          </ul>
        </nav>
      </aside>
    `;
  }

  getQQBotsHTML() {
    return `
      <div class="cute-card">
        <div class="card-header">
          <h3 class="card-title">🤖 官方 QQ 机器人</h3>
        </div>
        <form class="cute-form" style="margin-bottom: 24px;">
          <div class="cute-form-row">
            <div class="cute-form-group">
              <label class="cute-label">App ID</label>
              <input type="text" class="cute-input" placeholder="请输入 App ID">
            </div>
            <div class="cute-form-group">
              <label class="cute-label">Secret</label>
              <input type="text" class="cute-input" placeholder="请输入 Secret">
            </div>
            <div class="cute-form-group">
              <label class="cute-label">&nbsp;</label>
              <button type="submit" class="cute-btn cute-btn-primary" style="width: 100%;">
                ➕ 添加
              </button>
            </div>
          </div>
        </form>
        
        <table class="cute-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>App ID</th>
              <th>状态</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>1</td>
              <td><code>123456789</code></td>
              <td><span class="cute-badge cute-badge-success">正常</span></td>
              <td><button class="cute-btn cute-btn-danger cute-btn-sm delete-btn">🗑️ 删除</button></td>
            </tr>
            <tr>
              <td>2</td>
              <td><code>987654321</code></td>
              <td><span class="cute-badge cute-badge-warning">沙箱</span></td>
              <td><button class="cute-btn cute-btn-danger cute-btn-sm delete-btn">🗑️ 删除</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    `;
  }

  getNapcatBotsHTML() {
    return `
      <div class="cute-card">
        <div class="card-header">
          <h3 class="card-title">💻 NapCat 机器人</h3>
        </div>
        <form class="cute-form" style="margin-bottom: 24px;">
          <div class="cute-form-row">
            <div class="cute-form-group">
              <label class="cute-label">QQ 号</label>
              <input type="text" class="cute-input" placeholder="请输入 QQ 号">
            </div>
            <div class="cute-form-group">
              <label class="cute-label">HTTP 地址</label>
              <input type="text" class="cute-input" placeholder="http://127.0.0.1:3000">
            </div>
            <div class="cute-form-group">
              <label class="cute-label">&nbsp;</label>
              <button type="submit" class="cute-btn cute-btn-success" style="width: 100%;">
                ➕ 添加
              </button>
            </div>
          </div>
        </form>
        
        <table class="cute-table">
          <thead>
            <tr>
              <th>QQ</th>
              <th>地址</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>10001</code></td>
              <td>http://127.0.0.1:3000</td>
              <td><button class="cute-btn cute-btn-danger cute-btn-sm delete-btn">🗑️ 删除</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    `;
  }

  getLogsHTML() {
    return `
      <div class="cute-card">
        <div class="card-header">
          <h3 class="card-title">📨 消息日志</h3>
        </div>
        <div style="overflow-x: auto;">
          <table class="cute-table">
            <thead>
              <tr>
                <th>类型</th>
                <th>用户</th>
                <th>内容</th>
                <th>时间</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><span class="cute-badge cute-badge-primary">QQ</span></td>
                <td>12345</td>
                <td>你好呀~</td>
                <td>12:34:56</td>
              </tr>
              <tr>
                <td><span class="cute-badge cute-badge-success">NapCat</span></td>
                <td>67890</td>
                <td>萌化了!</td>
                <td>12:35:00</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    `;
  }

  getSystemHTML() {
    return `
      <div class="cute-card">
        <div class="card-header">
          <h3 class="card-title">📋 系统日志</h3>
        </div>
        <table class="cute-table">
          <thead>
            <tr>
              <th>级别</th>
              <th>消息</th>
              <th>时间</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><span class="cute-badge cute-badge-info">INFO</span></td>
              <td>系统启动成功~ 🌸</td>
              <td>12:00:00</td>
            </tr>
            <tr>
              <td><span class="cute-badge cute-badge-success">OK</span></td>
              <td>数据库连接正常</td>
              <td>12:00:01</td>
            </tr>
          </tbody>
        </table>
      </div>
    `;
  }

  getSettingsHTML() {
    return `
      <div class="cute-card">
        <div class="card-header">
          <h3 class="card-title">⚙️ 系统设置</h3>
        </div>
        <form class="cute-form">
          <div class="cute-form-group">
            <label class="cute-label">站点名称</label>
            <input type="text" class="cute-input" value="Sheng_Bot">
          </div>
          <div class="cute-form-row">
            <div class="cute-form-group">
              <label class="cute-label">HTTP 端口</label>
              <input type="number" class="cute-input" value="9501">
            </div>
            <div class="cute-form-group">
              <label class="cute-label">HTTPS 端口</label>
              <input type="number" class="cute-input" value="9502">
            </div>
          </div>
          <button type="submit" class="cute-btn cute-btn-primary">
            💾 保存设置
          </button>
        </form>
      </div>
    `;
  }

  // ================== 📨 Form Handling ==================
  handleFormSubmit(form) {
    this.showToast('success', '保存成功!', '您的设置已保存~');
  }

  handleDelete(btn) {
    if (confirm('确定要删除吗? 😢')) {
      this.showToast('success', '已删除!', '数据已移除');
      const row = btn.closest('tr');
      if (row) {
        row.style.transition = 'all 0.5s';
        row.style.opacity = '0';
        row.style.transform = 'scale(0.9)';
        setTimeout(() => row.remove(), 500);
      }
    }
  }

  handleCopy(btn) {
    this.showToast('success', '复制成功!', '内容已复制到剪贴板');
  }

  // ================== ✨ Effects ==================
  showLoading(show) {
    if (show) {
      const overlay = document.createElement('div');
      overlay.id = 'loading-overlay';
      overlay.style.cssText = `
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(255,240,245,0.95);
        display: flex; align-items: center; justify-content: center;
        z-index: 99999;
      `;
      overlay.innerHTML = `
        <div class="loading-spinner">
          <div class="loading-dot"></div>
          <div class="loading-dot"></div>
          <div class="loading-dot"></div>
        </div>
      `;
      document.body.appendChild(overlay);
    } else {
      const overlay = document.getElementById('loading-overlay');
      if (overlay) overlay.remove();
    }
  }

  showToast(type, title, message) {
    const container = document.querySelector('.cute-toast-container') || this.createToastContainer();
    
    const icons = { success: '✨', error: '😢', warning: '⚠️', info: '💡' };
    const colors = { success: '#90EE90', error: '#FF6B6B', warning: '#FFD700', info: '#87CEEB' };
    
    const toast = document.createElement('div');
    toast.className = 'cute-toast';
    toast.style.borderLeftColor = colors[type];
    toast.innerHTML = `
      <span class="toast-icon">${icons[type]}</span>
      <div>
        <div style="font-weight: 700; color: ${colors[type]};">${title}</div>
        <div style="font-size: 0.9rem; color: #666;">${message}</div>
      </div>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
      toast.style.animation = 'toast-in 0.3s reverse forwards';
      setTimeout(() => toast.remove(), 300);
    }, 4000);
  }

  createToastContainer() {
    const container = document.createElement('div');
    container.className = 'cute-toast-container';
    document.body.appendChild(container);
    return container;
  }

  createSparkles() {
    for (let i = 0; i < 30; i++) {
      setTimeout(() => this.createSingleSparkle(), i * 50);
    }
    this.showToast('success', '好萌!', '✨ 樱吹雪 ✨');
  }

  createSingleSparkle() {
    const sparkle = document.createElement('div');
    sparkle.className = 'sparkle';
    sparkle.style.left = `${Math.random() * window.innerWidth}px`;
    sparkle.style.top = `${Math.random() * window.innerHeight}px`;
    sparkle.style.animationDelay = `${Math.random() * 1}s`;
    document.body.appendChild(sparkle);
    setTimeout(() => sparkle.remove(), 2500);
  }

  delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}

// ================== 🌸 Initialize ==================
let app;
document.addEventListener('DOMContentLoaded', () => {
  app = new ShengBotApp();
});
