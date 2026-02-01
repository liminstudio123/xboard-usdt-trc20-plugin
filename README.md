# USDT TRC20 Payment Plugin for Xboard

USDT TRC20 支付插件，集成 auto-receive-crypto-pay 支付监控系统。

## 功能特性

- ✅ 集成 auto-receive-crypto-pay 支付监控
- ✅ 小数金额标记自动识别订单
- ✅ 支持 Tron 主网和测试网
- ✅ 实时交易验证和确认
- ✅ 完全自托管，无第三方依赖

## 安装步骤

1. 下载 `USDTTRC20.zip` 文件
2. 在 Xboard 后台进入 **插件管理** → **上传插件**
3. 选择下载的 ZIP 文件上传
4. 系统自动解压安装

## 配置参数

上传后，点击"配置"按钮填写以下参数：

| 参数 | 说明 | 示例 |
|------|------|------|
| 启用 | 是否启用此支付方式 | ✓ |
| 显示名称 | 用户看到的支付方式名称 | USDT (TRC20) |
| 图标 | emoji 图标 | 💎 |
| auto-receive-crypto-pay 地址 | 你的支付监控系统地址 | http://pay.vaultway.net |
| Webhook 密钥 | auto-receive-crypto-pay 中的 auth 值 | abc |
| 区块链网络 | 选择 Tron 主网或测试网 | Tron 主网 |
| 确认块数 | 交易确认数 | 25 |

## 工作原理
