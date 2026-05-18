# Sheng_Bot 全面功能测试 - 验证检查清单

## 后端 API 测试

- [ ] GET /admin/api/stats 返回正确的数据结构
- [ ] GET /admin/api/qq-bots 返回机器人列表
- [ ] GET /admin/api/napcat-bots 返回机器人列表
- [ ] GET /admin/api/message-logs 返回日志
- [ ] GET /admin/api/system-logs 返回日志
- [ ] GET /admin/api/settings 返回设置
- [ ] GET /admin/api/csrf-token 返回令牌
- [ ] 所有 API 返回正确的 HTTP 状态码

## 核心模块测试

- [ ] SQLite 数据库连接正常
- [ ] 数据库 CRUD 操作正常
- [ ] 数据库连接池功能正常
- [ ] 查询缓存功能正常
- [ ] Session 管理功能正常
- [ ] 日志系统记录功能正常

## 机器人管理测试

- [ ] 可以添加 QQ 机器人
- [ ] 可以删除 QQ 机器人
- [ ] 可以添加 NapCat 机器人
- [ ] 可以删除 NapCat 机器人
- [ ] 消息测试功能正常工作
- [ ] 操作正确记录到系统日志

## 前端页面测试

- [ ] /admin/index.html 可以正常访问
- [ ] /admin/cute-app.js 可以正常访问
- [ ] /admin/cute-theme.css 可以正常访问
- [ ] /admin/styles.css 可以正常访问
- [ ] 所有页面文件内容完整
- [ ] 页面静态资源加载正常

## 完整性检查

- [ ] 所有功能都经过测试
- [ ] 测试结果完整记录
- [ ] 报告清晰可读
- [ ] 所有问题都已记录

