/* ========================================
   🌸 S H E N G _ B O T  前 端 交 互 🌸
   No Refresh, All Fun! ✨
   ======================================== */

class ShengBotApp {
  constructor() {
    this.currentPage = 'dashboard';
    this.stats = null;
    this.qqBots = [];
    this.napcatBots = [];
    this.messageLogs = [];
    this.systemLogs = [];
    this.settings = {};
    this.init();
  }

  async init() {
    this.createPetals();
    this.bindEvents();
    await this.loadDashboardData();
    this.updatePageContent('dashboard');
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

  // ================== 🌐 API Helper ==================
  async fetchApi(endpoint, options = {}) {
    try {
      const response = await fetch(`/admin/api/${endpoint}`, {
        headers: {
          'Content-Type': 'application/json',
          ...options.headers
        },
        ...options
      });
      return await response.json();
    } catch (error) {
      this.showToast('error', '请求失败', error.message);
      throw error;
    }
  }

  // ================== 🎯 Event Binding ==================
  bindEvents() {
    document.addEventListener('submit', async (e) => {
      const form = e.target;
      if (form.matches('.cute-form')) {
        e.preventDefault();
        await this.handleFormSubmit(form);
      }
    });

    document.addEventListener('click', async (e) => {
      const navLink = e.target.closest('.cute-nav-link');
      if (navLink) {
        e.preventDefault();
        const page = navLink.dataset.page;
        if (page) {
          await this.navigateTo(page);
        }
        return;
      }
      
      const deleteBtn = e.target.closest('.delete-btn');
      if (deleteBtn) {
        e.preventDefault();
        await this.handleDelete(deleteBtn);
        return;
      }
      
      const copyBtn = e.target.closest('.copy-btn');
      if (copyBtn) {
        this.handleCopy(copyBtn);
        return;
      }
      
      const refreshBtn = e.target.closest('.refresh-btn');
      if (refreshBtn) {
        e.preventDefault();
        await this.refreshCurrentPage();
        return;
      }
    });
  }

  // ================== 🧭 Navigation ==================
  async navigateTo(page) {
    this.currentPage = page;
    
    document.querySelectorAll('.cute-nav-link').forEach(link => {
      link.classList.remove('active');
      if (link.dataset.page === page) {
        link.classList.add('active');
      }
    });

    this.showLoading(true);
    
    try {
      await this.loadPageData(page);
      this.updatePageContent(page);
    } catch (error) {
      console.error('Failed to load page:', error);
    }
    
    this.showLoading(false);
    this.createSparkles();
  }

  async loadDashboardData() {
    try {
      this.stats = await this.fetchApi('stats');
    } catch (error) {
      console.error('Failed to load stats:', error);
    }
  }

  async loadPageData(page) {
    switch (page) {
      case 'dashboard':
        await this.loadDashboardData();
        break;
      case 'qqBots':
        const qqData = await this.fetchApi('qq-bots');
        this.qqBots = qqData.bots || [];
        break;
      case 'napcatBots':
        const napcatData = await this.fetchApi('napcat-bots');
        this.napcatBots = napcatData.bots || [];
        break;
      case 'logs':
        const msgData = await this.fetchApi('message-logs');
        this.messageLogs = msgData.logs || [];
        break;
      case 'system':
        const sysData = await this.fetchApi('system-logs');
        this.systemLogs = sysData.logs || [];
        break;
      case 'settings':
        const settingsData = await this.fetchApi('settings');
        this.settings = settingsData.settings || {};
        break;
    }
  }

  async refreshCurrentPage() {
    this.showLoading(true);
    await this.loadPageData(this.currentPage);
    this.updatePageContent(this.currentPage);
    this.showLoading(false);
    this.showToast('success', '刷新成功', '数据已更新');
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
  }

  // ================== 📄 Page Templates ==================
  getDashboardHTML() {
    const stats = this.stats || { qqBots: 0, napcatBots: 0, messageLogs: 0, systemLogs: 0, phpVersion: 'Unknown', swooleVersion: 'Unknown' };
    
    return `
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-value">${stats.qqBots}</div>
          <div class="stat-label">🤖 QQ 机器人</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">${stats.napcatBots}</div>
          <div class="stat-label">💻 NapCat 机器人</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">${stats.messageLogs}</div>
          <div class="stat-label">📨 消息日志</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">${stats.systemLogs}</div>
          <div class="stat-label">📋 系统日志</div>
        </div>
      </div>
      
      <div class="cute-card">
        <div class="card-header">
          <h3 class="card-title">✨ 快速操作</h3>
        </div>
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
          <a href="javascript:void(0)" class="cute-btn cute-btn-primary cute-nav-link" data-page="qqBots">
            🚀 管理 QQ 机器人
          </a>
          <a href="javascript:void(0)" class="cute-btn cute-btn-success cute-nav-link" data-page="napcatBots">
            🔧 管理 NapCat 机器人
          </a>
          <button class="cute-btn cute-btn-primary refresh-btn">
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
              <td><span class="cute-badge cute-badge-primary">${stats.phpVersion}</span></td>
            </tr>
            <tr>
              <td>⚡ Swoole 版本</td>
              <td><span class="cute-badge cute-badge-success">${stats.swooleVersion}</span></td>
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

  getQQBotsHTML() {
    const botsHTML = this.qqBots.map(bot => `
      <tr>
        <td>${bot.id}</td>
        <td><code>${bot.appid}</code></td>
        <td><span class="cute-badge ${bot.sandbox ? 'cute-badge-warning' : 'cute-badge-success'}">${bot.sandbox ? '沙箱' : '正式'}</span></td>
        <td><button class="cute-btn cute-btn-danger cute-btn-sm delete-btn" data-type="qq" data-id="${bot.id}">🗑️ 删除</button></td>
      </tr>
    `).join('');

    return `
      <div class="cute-card">
        <div class="card-header">
          <h3 class="card-title">🤖 官方 QQ 机器人</h3>
          <button class="cute-btn cute-btn-success cute-btn-sm refresh-btn">🔄 刷新</button>
        </div>
        <form class="cute-form" data-form-type="qq-bot" style="margin-bottom: 24px;">
          <div class="cute-form-row">
            <div class="cute-form-group">
              <label class="cute-label">App ID</label>
              <input type="text" class="cute-input" name="appid" placeholder="请输入 App ID" required>
            </div>
            <div class="cute-form-group">
              <label class="cute-label">Secret</label>
              <input type="text" class="cute-input" name="secret" placeholder="请输入 Secret" required>
            </div>
            <div class="cute-form-group">
              <label class="cute-label">沙箱模式</label>
              <select class="cute-select" name="sandbox">
                <option value="1">是</option>
                <option value="0">否</option>
              </select>
            </div>
          </div>
          <button type="submit" class="cute-btn cute-btn-primary">
            ➕ 添加 QQ 机器人
          </button>
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
            ${botsHTML || '<tr><td colspan="4" style="text-align: center; color: #999;">暂无数据</td></tr>'}
          </tbody>
        </table>
      </div>
    `;
  }

  getNapcatBotsHTML() {
    const botsHTML = this.napcatBots.map(bot => `
      <tr>
        <td><code>${bot.qq}</code></td>
        <td>${bot.http_url}</td>
        <td>${bot.token ? `<code>${bot.token.substring(0, 10)}...</code>` : '-'}</td>
        <td><button class="cute-btn cute-btn-danger cute-btn-sm delete-btn" data-type="napcat" data-id="${bot.id}">🗑️ 删除</button></td>
      </tr>
    `).join('');

    return `
      <div class="cute-card">
        <div class="card-header">
          <h3 class="card-title">💻 NapCat 机器人</h3>
          <button class="cute-btn cute-btn-success cute-btn-sm refresh-btn">🔄 刷新</button>
        </div>
        <form class="cute-form" data-form-type="napcat-bot" style="margin-bottom: 24px;">
          <div class="cute-form-row">
            <div class="cute-form-group">
              <label class="cute-label">QQ 号</label>
              <input type="text" class="cute-input" name="qq" placeholder="请输入 QQ 号" required>
            </div>
            <div class="cute-form-group">
              <label class="cute-label">HTTP 地址</label>
              <input type="text" class="cute-input" name="http_url" placeholder="http://127.0.0.1:3000" required>
            </div>
            <div class="cute-form-group">
              <label class="cute-label">Token (可选)</label>
              <input type="text" class="cute-input" name="token" placeholder="请输入 Token">
            </div>
          </div>
          <button type="submit" class="cute-btn cute-btn-success">
            ➕ 添加 NapCat 机器人
          </button>
        </form>
        
        <table class="cute-table">
          <thead>
            <tr>
              <th>QQ</th>
              <th>HTTP 地址</th>
              <th>Token</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
            ${botsHTML || '<tr><td colspan="4" style="text-align: center; color: #999;">暂无数据</td></tr>'}
          </tbody>
        </table>
      </div>
    `;
  }

  getLogsHTML() {
    const logsHTML = this.messageLogs.map(log => `
      <tr>
        <td><span class="cute-badge cute-badge-primary">${log.bot_type}</span></td>
        <td>${log.bot_id}</td>
        <td>${log.user_id || '-'}</td>
        <td>${log.group_id || '-'}</td>
        <td>${log.content || '-'}</td>
        <td>${log.created_at || '-'}</td>
      </tr>
    `).join('');

    return `
      <div class="cute-card">
        <div class="card-header">
          <h3 class="card-title">📨 消息日志</h3>
          <button class="cute-btn cute-btn-success cute-btn-sm refresh-btn">🔄 刷新</button>
        </div>
        <div style="overflow-x: auto;">
          <table class="cute-table">
            <thead>
              <tr>
                <th>类型</th>
                <th>Bot ID</th>
                <th>用户</th>
                <th>群组</th>
                <th>内容</th>
                <th>时间</th>
              </tr>
            </thead>
            <tbody>
              ${logsHTML || '<tr><td colspan="6" style="text-align: center; color: #999;">暂无数据</td></tr>'}
            </tbody>
          </table>
        </div>
      </div>
    `;
  }

  getSystemHTML() {
    const logsHTML = this.systemLogs.map(log => `
      <tr>
        <td><span class="cute-badge ${this.getLevelBadge(log.level)}">${log.level}</span></td>
        <td>${log.message}</td>
        <td>${log.created_at || '-'}</td>
      </tr>
    `).join('');

    return `
      <div class="cute-card">
        <div class="card-header">
          <h3 class="card-title">📋 系统日志</h3>
          <button class="cute-btn cute-btn-success cute-btn-sm refresh-btn">🔄 刷新</button>
        </div>
        <div style="overflow-x: auto;">
          <table class="cute-table">
            <thead>
              <tr>
                <th>级别</th>
                <th>消息</th>
                <th>时间</th>
              </tr>
            </thead>
            <tbody>
              ${logsHTML || '<tr><td colspan="3" style="text-align: center; color: #999;">暂无数据</td></tr>'}
            </tbody>
          </table>
        </div>
      </div>
    `;
  }

  getLevelBadge(level) {
    const badges = {
      'info': 'cute-badge-info',
      'success': 'cute-badge-success',
      'warning': 'cute-badge-warning',
      'error': 'cute-badge-danger'
    };
    return badges[level] || 'cute-badge-primary';
  }

  getSettingsHTML() {
    const settings = this.settings || {};
    return `
      <div class="cute-card">
        <div class="card-header">
          <h3 class="card-title">⚙️ 系统设置</h3>
        </div>
        <form class="cute-form" data-form-type="settings">
          <div class="cute-form-group">
            <label class="cute-label">站点名称</label>
            <input type="text" class="cute-input" name="site_name" value="${settings.site_name || 'Sheng_Bot'}">
          </div>
          
          <div class="cute-form-group">
            <label class="cute-label">域名</label>
            <input type="text" class="cute-input" name="domain" value="${settings.domain || '0.0.0.0'}">
          </div>
          
          <div class="cute-form-row">
            <div class="cute-form-group">
              <label class="cute-label">HTTP 端口</label>
              <input type="number" class="cute-input" name="http_port" value="${settings.http_port || 9501}">
            </div>
            <div class="cute-form-group">
              <label class="cute-label">HTTPS 端口</label>
              <input type="number" class="cute-input" name="https_port" value="${settings.https_port || 9502}">
            </div>
          </div>
          
          <div class="cute-card" style="background: rgba(255,245,250,0.5); margin-top: 24px; margin-bottom: 24px;">
            <h4 style="color: var(--deep-pink); margin-bottom: 16px;">🗄️ 数据库连接池</h4>
            <div class="cute-form-row">
              <div class="cute-form-group">
                <label class="cute-label">最大连接数</label>
                <input type="number" class="cute-input" name="db_pool_max_size" value="${settings.db_pool_max_size || 10}">
              </div>
              <div class="cute-form-group">
                <label class="cute-label">最小连接数</label>
                <input type="number" class="cute-input" name="db_pool_min_size" value="${settings.db_pool_min_size || 2}">
              </div>
              <div class="cute-form-group">
                <label class="cute-label">超时时间 (秒)</label>
                <input type="number" class="cute-input" name="db_pool_timeout" value="${settings.db_pool_timeout || 5}">
              </div>
            </div>
          </div>
          
          <div class="cute-card" style="background: rgba(240,255,245,0.5); margin-bottom: 24px;">
            <h4 style="color: var(--deep-pink); margin-bottom: 16px;">💾 查询缓存</h4>
            <div class="cute-form-row">
              <div class="cute-form-group">
                <label class="cute-label">启用缓存</label>
                <select class="cute-select" name="query_cache_enabled">
                  <option value="true" ${settings.query_cache_enabled ? 'selected' : ''}>是</option>
                  <option value="false" ${!settings.query_cache_enabled ? 'selected' : ''}>否</option>
                </select>
              </div>
              <div class="cute-form-group">
                <label class="cute-label">缓存 TTL (秒)</label>
                <input type="number" class="cute-input" name="query_cache_ttl" value="${settings.query_cache_ttl || 300}">
              </div>
              <div class="cute-form-group">
                <label class="cute-label">最大缓存条数</label>
                <input type="number" class="cute-input" name="query_cache_max_size" value="${settings.query_cache_max_size || 1000}">
              </div>
            </div>
          </div>
          
          <div class="cute-card" style="background: rgba(245,240,255,0.5); margin-bottom: 24px;">
            <h4 style="color: var(--deep-pink); margin-bottom: 16px;">📝 日志系统</h4>
            <div class="cute-form-row">
              <div class="cute-form-group">
                <label class="cute-label">日志级别</label>
                <select class="cute-select" name="log_level">
                  <option value="debug" ${settings.log_level === 'debug' ? 'selected' : ''}>Debug</option>
                  <option value="info" ${settings.log_level === 'info' ? 'selected' : ''}>Info</option>
                  <option value="warning" ${settings.log_level === 'warning' ? 'selected' : ''}>Warning</option>
                  <option value="error" ${settings.log_level === 'error' ? 'selected' : ''}>Error</option>
                </select>
              </div>
              <div class="cute-form-group">
                <label class="cute-label">最大文件大小 (MB)</label>
                <input type="number" class="cute-input" name="log_max_file_size" value="${settings.log_max_file_size || 10}">
              </div>
              <div class="cute-form-group">
                <label class="cute-label">最大文件数</label>
                <input type="number" class="cute-input" name="log_max_files" value="${settings.log_max_files || 10}">
              </div>
            </div>
            <div class="cute-form-row">
              <div class="cute-form-group">
                <label class="cute-label">日志写入数据库</label>
                <select class="cute-select" name="log_to_database">
                  <option value="true" ${settings.log_to_database ? 'selected' : ''}>是</option>
                  <option value="false" ${!settings.log_to_database ? 'selected' : ''}>否</option>
                </select>
              </div>
              <div class="cute-form-group">
                <label class="cute-label">日志写入文件</label>
                <select class="cute-select" name="log_to_file">
                  <option value="true" ${settings.log_to_file ? 'selected' : ''}>是</option>
                  <option value="false" ${!settings.log_to_file ? 'selected' : ''}>否</option>
                </select>
              </div>
            </div>
          </div>
          
          <div style="display: flex; gap: 12px;">
            <button type="submit" class="cute-btn cute-btn-primary">
              💾 保存设置
            </button>
            <button type="button" class="cute-btn cute-btn-success refresh-btn">
              🔄 重置
            </button>
          </div>
        </form>
      </div>
    `;
  }

  // ================== 📨 Form Handling ==================
  async handleFormSubmit(form) {
    const formType = form.dataset.formType;
    
    try {
      if (formType === 'qq-bot') {
        const formData = new FormData(form);
        const result = await this.fetchApi('qq-bots', {
          method: 'POST',
          body: formData
        });
        
        if (result.success !== false) {
          this.showToast('success', '添加成功', result.message || 'QQ机器人添加成功');
          await this.refreshCurrentPage();
        } else {
          this.showToast('error', '添加失败', result.error || '未知错误');
        }
      } else if (formType === 'napcat-bot') {
        const formData = new FormData(form);
        const result = await this.fetchApi('napcat-bots', {
          method: 'POST',
          body: formData
        });
        
        if (result.success !== false) {
          this.showToast('success', '添加成功', result.message || 'NapCat机器人添加成功');
          await this.refreshCurrentPage();
        } else {
          this.showToast('error', '添加失败', result.error || '未知错误');
        }
      } else if (formType === 'settings') {
        const formData = new FormData(form);
        const data = {};
        formData.forEach((value, key) => {
          if (value === 'true') data[key] = true;
          else if (value === 'false') data[key] = false;
          else if (!isNaN(value) && value !== '') data[key] = Number(value);
          else data[key] = value;
        });
        
        const result = await this.fetchApi('settings', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: Object.keys(data).map(key => encodeURIComponent(key) + '=' + encodeURIComponent(data[key])).join('&')
        });
        
        if (result.success !== false) {
          this.showToast('success', '保存成功', result.message || '系统设置已保存');
        } else {
          this.showToast('error', '保存失败', result.error || '未知错误');
        }
      }
    } catch (error) {
      this.showToast('error', '操作失败', error.message);
    }
  }

  async handleDelete(btn) {
    const type = btn.dataset.type;
    const id = btn.dataset.id;
    
    if (!confirm('确定要删除吗? 😢')) {
      return;
    }
    
    try {
      const endpoint = type === 'qq' ? `qq-bots/${id}` : `napcat-bots/${id}`;
      const result = await this.fetchApi(endpoint, {
        method: 'DELETE'
      });
      
      if (result.success !== false) {
        this.showToast('success', '删除成功', result.message || '已删除');
        await this.refreshCurrentPage();
      } else {
        this.showToast('error', '删除失败', result.error || '未知错误');
      }
    } catch (error) {
      this.showToast('error', '删除失败', error.message);
    }
  }

  handleCopy(btn) {
    this.showToast('success', '复制成功', '内容已复制到剪贴板');
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
      toast.style.animation = 'toast-in 0.4s var(--transition-bounce) reverse forwards';
      setTimeout(() => toast.remove(), 400);
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
