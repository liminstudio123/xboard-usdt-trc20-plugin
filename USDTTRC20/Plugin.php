<?php

namespace Plugin\USDTTRC20;

use App\Services\Plugin\AbstractPlugin;
use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class Plugin extends AbstractPlugin implements PaymentInterface
{
    public function boot(): void
    {
        $this->filter('available_payment_methods', function($methods) {
            if ($this->getConfig('enabled', true)) {
                $methods['USDTTRC20'] = [
                    'name' => $this->getConfig('display_name', 'USDT (TRC20)'),
                    'icon' => $this->getConfig('icon', '💎'),
                    'plugin_code' => $this->getPluginCode(),
                    'type' => 'plugin'
                ];
            }
            return $methods;
        });
    }

    public function form(): array
    {
        return [
            'enabled' => [
                'label' => '启用此支付方式',
                'type' => 'checkbox',
                'default' => true
            ],
            'display_name' => [
                'label' => '显示名称',
                'type' => 'string',
                'default' => 'USDT (TRC20)'
            ],
            'icon' => [
                'label' => '图标',
                'type' => 'string',
                'default' => '💎'
            ],
            'pay_server_url' => [
                'label' => 'auto-receive-crypto-pay 地址',
                'type' => 'string',
                'required' => true
            ],
            'pay_server_auth' => [
                'label' => 'Webhook 密钥',
                'type' => 'string',
                'required' => true
            ],
            'network' => [
                'label' => '区块链网络',
                'type' => 'select',
                'default' => 'TRON_MAINNET',
                'options' => [
                    'TRON_MAINNET' => 'Tron 主网',
                    'TRON_TESTNET' => 'Tron 测试网'
                ]
            ],
            'confirm_blocks' => [
                'label' => '确认块数',
                'type' => 'string',
                'default' => '25'
            ]
        ];
    }

    public function pay($order): array
    {
        try {
            $baseAmount = $order['total_amount'] / 100;
            $decimalMark = $this->generateDecimalMark($order['trade_no']);
            $payAmount = number_format($baseAmount + $decimalMark, 6, '.', '');

            $address = $this->getPaymentAddress();
            if (!$address) {
                throw new ApiException('无法获取收款地址');
            }

            $this->saveOrder($order['trade_no'], $payAmount);

            $qrcode = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . 
                     urlencode("tron:{$address}?amount={$payAmount}");

            return [
                'type' => 0,
                'data' => [
                    'address' => $address,
                    'amount' => $payAmount,
                    'qrcode' => $qrcode,
                    'instruction' => "请向地址转账精确金额 {$payAmount} USDT"
                ]
            ];
        } catch (\Exception $e) {
            Log::error('USDT 支付失败', ['error' => $e->getMessage()]);
            throw new ApiException($e->getMessage());
        }
    }

    public function notify($params): array|bool
    {
        try {
            $fromAddress = $params['from_address'] ?? null;
            $amount = $params['amount'] ?? 0;
            $txHash = $params['tx_hash'] ?? null;

            if (!$fromAddress || !$amount || !$txHash) {
                return false;
            }

            $order = $this->findOrderByAmount($amount);
            if (!$order) {
                return false;
            }

            DB::table('usdt_trc20_orders')
                ->where('trade_no', $order->trade_no)
                ->update([
                    'tx_hash' => $txHash,
                    'status' => 'paid',
                    'paid_at' => now()
                ]);

            return [
                'trade_no' => $order->trade_no,
                'callback_no' => $txHash
            ];
        } catch (\Exception $e) {
            Log::error('USDT 支付验证失败', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function generateDecimalMark($tradeNo): float
    {
        $numStr = preg_replace('/[^0-9]/', '', $tradeNo);
        $lastDigits = substr($numStr, -5) ?: '1';
        $lastDigits = str_pad($lastDigits, 5, '0', STR_PAD_LEFT);
        return (float)('0.' . $lastDigits);
    }

    private function getPaymentAddress(): ?string
    {
        try {
            $url = rtrim($this->getConfig('pay_server_url'), '/') . '/gin/pay';
            $response = Http::timeout(10)->get($url);
            if ($response->failed()) {
                return null;
            }
            $html = $response->body();
            if (preg_match('/data-address="([^"]*)"/', $html, $matches)) {
                return $matches[1];
            }
            return null;
        } catch (\Exception $e) {
            Log::error('获取支付地址失败', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function saveOrder($tradeNo, $amount): void
    {
        try {
            DB::table('usdt_trc20_orders')->insertOrIgnore([
                'trade_no' => $tradeNo,
                'amount' => $amount,
                'status' => 'pending',
                'created_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::warning('保存订单失败', ['error' => $e->getMessage()]);
        }
    }

    private function findOrderByAmount($amount)
    {
        return DB::table('usdt_trc20_orders')
            ->where('status', 'pending')
            ->where('created_at', '>', now()->subHours(2))
            ->whereRaw("ABS(CAST(amount AS DECIMAL(18,6)) - ?) < 0.000001", [$amount])
            ->first();
    }
}
