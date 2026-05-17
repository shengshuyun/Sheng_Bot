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
    this.csrfToken = null;
    this.init();
  }

  async init() {
    this.createPetals();
    this.bindEvents();
    await this.loadDashboardData();
    this.updatePageContent('dashboard');
    // 移除了自动弹出的欢迎提示
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
  async getCsrfToken() {
    if (this.csrfToken) {
      return this.csrfToken;
    }
    const data = await this.fetchApi('csrf-token', { method: 'GET' });
    this.csrfToken = data.csrf_token;
    return this.csrfToken;
  }

  async fetchApi(endpoint, options = {}) {
    try {
      const fetchOptions = { ...options };
      const method = (fetchOptions.method || 'GET').toUpperCase();
      
      // 只有当 body 不是 FormData 时才设置 Content-Type
      if (!(options.body instanceof FormData)) {
        fetchOptions.headers = {
          'Content-Type': 'application/x-www-form-urlencoded',
          ...options.headers
        };
      } else {
        // FormData 不需要手动设置 Content-Type
        fetchOptions.headers = { ...options.headers };
      }
      
      // 对非 GET 请求添加 CSRF 令牌
      if (method !== 'GET') {
        const token = await this.getCsrfToken();
        fetchOptions.headers['X-CSRF-Token'] = token;
      }
      
      const response = await fetch(`/admin/api/${endpoint}`, fetchOptions);
      return await response.json();
    } catch (error) {
      // 移除了烦人的Toast提示
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
        if (settingsData.csrf_token) {
          this.csrfToken = settingsData.csrf_token;
        }
        break;
    }
  }

  async refreshCurrentPage() {
    this.showLoading(true);
    await this.loadPageData(this.currentPage);
    this.updatePageContent(this.currentPage);
    this.showLoading(false);
    // 移除了烦人的刷新提示
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
      settings: this.getSettingsHTML(),
      test: this.getTestHTML()
    };

    mainContent.innerHTML = pages[page] || pages.dashboard;
    
    if (page === 'test') {
      this.loadTestPageData();
      this.bindTestEvents();
    }
  }

  // ================== 🧪 测试页功能 ==================
  async loadTestPageData() {
    await this.loadQQBots();
    this.updateBotSelect();
    this.handleTestEventTypeChange();
  }

  async loadQQBots() {
    try {
      const data = await this.fetchApi('qq-bots');
      this.qqBots = data.bots || [];
    } catch (e) {
      console.error('加载QQ机器人失败:', e);
    }
  }

  updateBotSelect() {
    const select = document.getElementById('test-bot-select');
    if (!select) return;
    select.innerHTML = '<option value="">-- 选择一个QQ机器人 --</option>';
    this.qqBots.forEach(bot => {
      const opt = document.createElement('option');
      opt.value = String(bot.id);
      opt.textContent = `${bot.appid} (${bot.sandbox ? '沙箱' : '正式'})`;
      select.appendChild(opt);
    });
  }

  addTestLog(msg, type = 'info') {
    const logDiv = document.getElementById('test-log');
    if (!logDiv) return;
    
    const entry = document.createElement('div');
    const colors = {
      info: '#4CAF50',
      success: '#4CAF50',
      error: '#FF6B6B',
      warning: '#FFD700'
    };
    const time = new Date().toLocaleTimeString();
    entry.style.cssText = `padding: 6px 8px; border-bottom: 1px solid #eee; color: ${colors[type] || '#333'};`;
    entry.textContent = `[${time}] ${msg}`;
    
    const placeholder = logDiv.querySelector('div[style*="color: #888"]');
    if (placeholder) placeholder.remove();
    
    logDiv.insertBefore(entry, logDiv.firstChild);
  }

  bindTestEvents() {
    const sendBtn = document.getElementById('test-send-btn');
    const refreshBtn = document.getElementById('test-refresh-bots-btn');
    const eventTypeSelect = document.getElementById('test-event-type-select');
    const runAllBtn = document.getElementById('test-run-all-btn');
    
    if (eventTypeSelect) {
      eventTypeSelect.onchange = () => this.handleTestEventTypeChange();
    }
    
    if (sendBtn) {
      sendBtn.onclick = () => this.sendTestMessage();
    }
    
    if (runAllBtn) {
      runAllBtn.onclick = () => this.runAllEventsTest();
    }
    
    if (refreshBtn) {
      refreshBtn.onclick = async () => {
        await this.loadQQBots();
        this.updateBotSelect();
        this.showToast('success', '刷新成功', '机器人列表已更新');
      };
    }
  }

  handleTestEventTypeChange() {
    const eventType = document.getElementById('test-event-type-select')?.value;
    const userFields = document.getElementById('test-user-fields');
    const groupField = document.getElementById('test-group-field');
    const channelFields = document.getElementById('test-channel-fields');
    const contentField = document.getElementById('test-content-field');
    const extraFields = document.getElementById('test-extra-fields');
    
    if (userFields) {
      userFields.style.display = 'flex';
    }
    
    if (groupField) {
      groupField.style.display = (
        eventType === 'GROUP_AT_MESSAGE_CREATE' ||
        eventType === 'GROUP_ADD_ROBOT' || 
        eventType === 'GROUP_DEL_ROBOT'
      ) ? '' : 'none';
    }
    
    if (channelFields) {
      channelFields.style.display = (
        eventType === 'DIRECT_MESSAGE_CREATE' || 
        eventType === 'AT_MESSAGE_CREATE' || 
        eventType === 'MESSAGE_CREATE' || 
        eventType === 'GUILD_CREATE' || 
        eventType === 'GUILD_DELETE'
      ) ? '' : 'none';
    }
    
    if (contentField) {
      contentField.style.display = (
        eventType === 'C2C_MESSAGE_CREATE' || 
        eventType === 'GROUP_AT_MESSAGE_CREATE' || 
        eventType === 'DIRECT_MESSAGE_CREATE' || 
        eventType === 'AT_MESSAGE_CREATE' || 
        eventType === 'MESSAGE_CREATE'
      ) ? '' : 'none';
    }
    
    if (extraFields) {
      extraFields.style.display = (
        eventType === 'AUTH_OP_13'
      ) ? '' : 'none';
    }
  }

  async sendTestMessage() {
    const botId = document.getElementById('test-bot-select')?.value;
    const eventType = document.getElementById('test-event-type-select')?.value;
    const senderId = document.getElementById('test-sender-id')?.value;
    const groupId = document.getElementById('test-group-id')?.value;
    const channelId = document.getElementById('test-channel-id')?.value;
    const guildId = document.getElementById('test-guild-id')?.value;
    const content = document.getElementById('test-content')?.value;
    const eventTs = document.getElementById('test-event-ts')?.value || Date.now().toString();
    const plainToken = document.getElementById('test-plain-token')?.value || 'test_token_123';

    if (!botId) {
      this.showToast('error', '错误', '请选择一个机器人');
      this.addTestLog('❌ 发送失败: 未选择机器人', 'error');
      return;
    }

    if (
      (eventType === 'GROUP_AT_MESSAGE_CREATE' || eventType === 'GROUP_ADD_ROBOT' || eventType === 'GROUP_DEL_ROBOT') && 
      !groupId
    ) {
      this.showToast('error', '错误', '群聊事件必须填写群号');
      this.addTestLog('❌ 发送失败: 群聊事件未填写群号', 'error');
      return;
    }

    if (
      (eventType === 'DIRECT_MESSAGE_CREATE' || eventType === 'AT_MESSAGE_CREATE' || 
       eventType === 'MESSAGE_CREATE' || eventType === 'GUILD_CREATE' || 
       eventType === 'GUILD_DELETE') && 
      (!channelId || !guildId)
    ) {
      this.showToast('error', '错误', '频道事件必须填写频道ID');
      this.addTestLog('❌ 发送失败: 频道事件未填写完整ID', 'error');
      return;
    }

    const requiresContent = eventType === 'C2C_MESSAGE_CREATE' || 
                           eventType === 'GROUP_AT_MESSAGE_CREATE' || 
                           eventType === 'DIRECT_MESSAGE_CREATE' || 
                           eventType === 'AT_MESSAGE_CREATE' || 
                           eventType === 'MESSAGE_CREATE';
    if (requiresContent && !content) {
      this.showToast('error', '错误', '请填写消息内容');
      this.addTestLog('❌ 发送失败: 消息内容为空', 'error');
      return;
    }

    try {
      this.addTestLog(`📤 正在发送事件: ${eventType}`, 'info');
      const result = await this.fetchApi('test/send-message', {
        method: 'POST',
        body: new URLSearchParams({
          bot_id: botId,
          event_type: eventType,
          sender_id: senderId,
          group_id: groupId,
          channel_id: channelId,
          guild_id: guildId,
          content: content,
          event_ts: eventTs,
          plain_token: plainToken
        })
      });

      if (result.success) {
        this.showToast('success', '发送成功', '测试事件已推送');
        this.addTestLog(`✅ 事件推送成功: ${eventType}`, 'success');
        
        if (result.event) {
          this.addTestLog(`📥 官方格式: `, 'info');
          this.addTestLog(JSON.stringify(result.event, null, 2));
        }
        
        if (result.processed !== undefined) {
          if (result.processed) {
            this.addTestLog(`🤖 机器人已处理！`, 'success');
            if (result.robot_response) {
              this.addTestLog(`📤 机器人返回: ${result.robot_response}`, 'info');
            }
            if (result.auth_response) {
              this.addTestLog(`🔐 鉴权响应: ${result.auth_response}`, 'success');
            }
          } else {
            this.addTestLog(`⚠️ 机器人处理: ${result.process_note || '未处理'}`, 'warning');
            if (result.process_error) {
              this.addTestLog(`❌ 错误: ${result.process_error}`, 'error');
            }
          }
        }
      } else {
        this.showToast('error', '发送失败', result.error || '未知错误');
        this.addTestLog(`❌ 事件推送失败: ${result.error}`, 'error');
      }
    } catch (e) {
      this.showToast('error', '发送失败', e.message);
      this.addTestLog(`❌ 异常: ${e.message}`, 'error');
    }
  }
  
  async runAllEventsTest() {
    const botId = document.getElementById('test-bot-select')?.value;
    
    if (!botId) {
      this.showToast('error', '错误', '请选择一个机器人');
      this.addTestLog('❌ 批量测试失败: 未选择机器人', 'error');
      return;
    }
    
    const events = [
      'C2C_MESSAGE_CREATE',
      'GROUP_AT_MESSAGE_CREATE',
      'DIRECT_MESSAGE_CREATE', 
      'AT_MESSAGE_CREATE',
      'MESSAGE_CREATE',
      'FRIEND_ADD',
      'FRIEND_DEL',
      'GROUP_ADD_ROBOT',
      'GROUP_DEL_ROBOT',
      'GUILD_CREATE',
      'GUILD_DELETE',
      'INTERACTION_CREATE',
      'MESSAGE_REACTION_ADD',
      'MESSAGE_REACTION_REMOVE',
      'AUTH_OP_13'
    ];
    
    this.addTestLog('🌊 开始批量测试所有事件...', 'info');
    this.showToast('info', '开始测试', '正在测试所有事件...');
    
    for (let i = 0; i < events.length; i++) {
      await this.delay(300);
      try {
        await this.fetchApi('test/send-message', {
          method: 'POST',
          body: new URLSearchParams({
            bot_id: botId,
            event_type: events[i],
            sender_id: 'test_user_' + i,
            group_id: 'test_group_' + i,
            channel_id: 'test_channel_' + i,
            guild_id: 'test_guild_' + i,
            content: '批量测试消息 #' + (i + 1),
            event_ts: Date.now().toString(),
            plain_token: 'test_token_' + i
          })
        });
        this.addTestLog(`✅ 测试事件 ${events[i]} 完成`, 'success');
      } catch (e) {
        this.addTestLog(`❌ 测试事件 ${events[i]} 失败: ${e.message}`, 'error');
      }
    }
    
    this.showToast('success', '测试完成', '所有事件测试完成！');
    this.addTestLog('🎉 批量测试完成！', 'success');
  }
  
  delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
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
          <a href="javascript:void(0)" class="cute-btn cute-btn-info cute-nav-link" data-page="test">
            🧪 消息测试
          </a>
          <button class="cute-btn cute-btn-primary refresh-btn">
            🔄 刷新数据
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
      const formData = new FormData(form);
      // 将 FormData 转换为 URLSearchParams 确保兼容性
      const params = new URLSearchParams();
      formData.forEach((value, key) => {
        params.append(key, value);
      });
      
      if (formType === 'qq-bot') {
        const result = await this.fetchApi('qq-bots', {
          method: 'POST',
          body: params
        });
        
        if (result.success !== false) {
          this.showToast('success', '添加成功', result.message || 'QQ机器人添加成功');
          await this.refreshCurrentPage();
        } else {
          this.showToast('error', '添加失败', result.error || '未知错误');
        }
      } else if (formType === 'napcat-bot') {
        const result = await this.fetchApi('napcat-bots', {
          method: 'POST',
          body: params
        });
        
        if (result.success !== false) {
          this.showToast('success', '添加成功', result.message || 'NapCat机器人添加成功');
          await this.refreshCurrentPage();
        } else {
          this.showToast('error', '添加失败', result.error || '未知错误');
        }
      } else if (formType === 'settings') {
        const data = {};
        formData.forEach((value, key) => {
          if (value === 'true') data[key] = true;
          else if (value === 'false') data[key] = false;
          else if (!isNaN(value) && value !== '') data[key] = Number(value);
          else data[key] = value;
        });
        
        const result = await this.fetchApi('settings', {
          method: 'POST',
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

  // ================== 🧪 测试功能 ==================
  getTestHTML() {
    return `
      <div class="cute-card">
        <div class="card-header">
          <h3 class="card-title">🧪 QQ 官方机器人完整事件测试</h3>
        </div>
        
        <div class="cute-form-row">
          <div class="cute-form-group">
            <label class="cute-label">选择机器人</label>
            <select class="cute-select" id="test-bot-select">
              <option value="">-- 选择一个QQ机器人 --</option>
            </select>
          </div>
          <div class="cute-form-group">
            <label class="cute-label">事件类型 (Event Type)</label>
            <select class="cute-select" id="test-event-type-select">
              <optgroup label="📨 消息类事件">
                <option value="C2C_MESSAGE_CREATE">单聊消息 (C2C_MESSAGE_CREATE)</option>
                <option value="GROUP_AT_MESSAGE_CREATE">群聊@ (GROUP_AT_MESSAGE_CREATE)</option>
                <option value="DIRECT_MESSAGE_CREATE">频道私信 (DIRECT_MESSAGE_CREATE)</option>
                <option value="AT_MESSAGE_CREATE">频道@ (AT_MESSAGE_CREATE)</option>
                <option value="MESSAGE_CREATE">频道消息 (MESSAGE_CREATE)</option>
              </optgroup>
              <optgroup label="👥 好友类事件">
                <option value="FRIEND_ADD">添加好友 (FRIEND_ADD)</option>
                <option value="FRIEND_DEL">删除好友 (FRIEND_DEL)</option>
              </optgroup>
              <optgroup label="🏠 群组/频道类事件">
                <option value="GROUP_ADD_ROBOT">加入群聊 (GROUP_ADD_ROBOT)</option>
                <option value="GROUP_DEL_ROBOT">退出群聊 (GROUP_DEL_ROBOT)</option>
                <option value="GUILD_CREATE">加入频道 (GUILD_CREATE)</option>
                <option value="GUILD_DELETE">退出频道 (GUILD_DELETE)</option>
              </optgroup>
              <optgroup label="🔘 互动类事件">
                <option value="INTERACTION_CREATE">按钮互动 (INTERACTION_CREATE)</option>
              </optgroup>
              <optgroup label="❤️ 表情类事件">
                <option value="MESSAGE_REACTION_ADD">添加表情 (MESSAGE_REACTION_ADD)</option>
                <option value="MESSAGE_REACTION_REMOVE">移除表情 (MESSAGE_REACTION_REMOVE)</option>
              </optgroup>
              <optgroup label="🔐 鉴权类事件">
                <option value="AUTH_OP_13">鉴权事件 (Op=13)</option>
              </optgroup>
            </select>
          </div>
        </div>
        
        <div class="cute-form-row" id="test-user-fields">
          <div class="cute-form-group">
            <label class="cute-label">发送者 ID (OpenID)</label>
            <input type="text" class="cute-input" id="test-sender-id" value="123456789">
          </div>
          <div class="cute-form-group" id="test-group-field">
            <label class="cute-label">群号 (Group OpenID)</label>
            <input type="text" class="cute-input" id="test-group-id" value="987654321">
          </div>
        </div>
        
        <div class="cute-form-row" id="test-channel-fields" style="display: none;">
          <div class="cute-form-group">
            <label class="cute-label">频道 ID (Guild ID)</label>
            <input type="text" class="cute-input" id="test-guild-id" value="guild_123">
          </div>
          <div class="cute-form-group">
            <label class="cute-label">子频道 ID (Channel ID)</label>
            <input type="text" class="cute-input" id="test-channel-id" value="channel_456">
          </div>
        </div>
        
        <div class="cute-form-group" id="test-content-field">
          <label class="cute-label">消息内容</label>
          <textarea class="cute-input" id="test-content" rows="4">你好，这是一条官方格式的测试消息！</textarea>
        </div>
        
        <div class="cute-form-row" id="test-extra-fields" style="display: none;">
          <div class="cute-form-group">
            <label class="cute-label">事件时间戳 (event_ts)</label>
            <input type="text" class="cute-input" id="test-event-ts" value="">
          </div>
          <div class="cute-form-group">
            <label class="cute-label">Plain Token</label>
            <input type="text" class="cute-input" id="test-plain-token" value="test_token_abc123">
          </div>
        </div>
        
        <div style="display: flex; gap: 12px; margin-top: 12px;">
          <button class="cute-btn cute-btn-primary" id="test-send-btn">
            🚀 发送测试消息
          </button>
          <button class="cute-btn cute-btn-success" id="test-refresh-bots-btn">
            🔄 刷新机器人列表
          </button>
          <button class="cute-btn cute-btn-info" id="test-run-all-btn">
            🌊 测试所有事件
          </button>
        </div>
      </div>
      
      <div class="cute-card" style="margin-top: 16px;">
        <div class="card-header">
          <h3 class="card-title">📋 推送记录</h3>
        </div>
        <div id="test-log" style="max-height: 500px; overflow-y: auto; font-family: monospace; font-size: 13px;">
          <div style="color: #888; padding: 8px;">暂无记录...</div>
        </div>
      </div>
    `;
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
