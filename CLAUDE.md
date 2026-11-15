# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build Commands

```bash
# Debug build
./gradlew assembleDebug

# Release build
./gradlew assembleRelease

# Install on connected Z108 terminal
adb install app/build/outputs/apk/debug/app-debug.apk

# Clean build
./gradlew clean assembleDebug
```

There are no automated tests in this project. Verification must be done on the Z108 physical device or via Android Studio's emulator (note: ZCS SDK features will be unavailable on emulator).

## Required SDK Files

Before building, place these three files in `app/libs/` (obtained from `SmartPos_2_0_4_R260318_SDK.zip`):
- `SmartPos_2.0.4_R260318.aar`
- `emv_2.0.2_R260309.aar`
- `core-3.2.1.jar`

The app builds and runs without them, but printer and second screen features will silently no-op.

## Architecture Overview

Single-Activity Android app using Jetpack Compose + single ViewModel pattern. Navigation is state-driven via a `Screen` sealed class in `MainViewModel`, not Jetpack Navigation.

**Data flow:**
```
AmountInputScreen → MainViewModel.startPayment()
  → PaymentRepository.fetchRates() [CoinGecko API]
  → PaymentRepository.createPayment() [Room DB insert]
  → BlockchainPaymentMonitor + DemoPaymentMonitor [parallel coroutine flows]
  → handleMonitorEvent() → Room DB update → StateFlow → QRDisplayScreen
```

**Key design decisions:**

- `MainViewModel` is the single source of truth. It holds `Screen`, `AmountUiState`, and `PaymentUiState` as `StateFlow`s.
- `BlockchainPaymentMonitor` and `DemoPaymentMonitor` run in parallel on every payment. The demo monitor fires only when the user long-presses the demo button; the blockchain monitor fires on real transactions. Whichever fires first drives the payment state.
- ZCS SDK (printer, second screen) is loaded reflectively at runtime — the app gracefully degrades when the SDK is absent. Check `isSdkAvailable` before assuming these work.
- `SecondScreenRenderer` renders Bitmap directly with `Canvas`; it must never display fee or operator information (customer-facing only). Fee data is only shown on the merchant main screen, receipt, and history.
- `BigDecimal` is used throughout for all crypto/fiat amounts. Room stores `BigDecimal` as `String` (see `PaymentEntity`).

**Layer responsibilities:**

| Layer | Package | Purpose |
|-------|---------|---------|
| UI | `ui/` | Compose screens + `MainViewModel` |
| Domain | `domain/` | `PaymentMonitor` interface, `BlockchainPaymentMonitor`, calculators |
| Data | `data/` | Room DB, API clients, `PaymentRepository`, models |
| Device | `device/` | ZCS SDK wrappers (printer, second screen) |
| Util | `util/` | `ConfigLoader` (reads `demo_config.json`), `QrGenerator` |

## Configuration

Runtime config is loaded from `app/src/main/assets/config/demo_config.json` via `ConfigLoader`. Key fields:

- `fee_percent` — operator fee taken from each payment (default 5.0%)
- `rate_markup_percent` — markup applied to CoinGecko rate before showing to customer (default 1.0%)
- `payment_timeout_minutes` — QR expiry (default 10 min)
- `merchant_*_address` / `operator_*_address` — receiving wallets per currency

`DemoConfig` is parsed by Gson; all fields have safe defaults so missing keys don't crash.

## Blockchain Monitoring

Each currency uses a different monitoring strategy in `BlockchainPaymentMonitor`:

- **XRP**: WebSocket subscription to `wss://xrplcluster.com` — push-based, instant finality on `validated=true`
- **ETH**: Polls Etherscan free API every 8 seconds; 1 confirmation = done
- **BTC**: Polls `mempool.space` every 10 seconds; 1 confirmation = done (async — customers don't wait at terminal)

## Fee Display Rules

This is a strict business rule enforced in multiple places:
- `SecondScreenRenderer` — must never render `merchantAmountJpy`, `operatorFeeJpy`, or fee percentages
- `QRDisplayScreen` — customer-visible; show JPY amount only
- Merchant screen, receipt (`MinaPayPrinter`), and `HistoryScreen` — may show full fee breakdown

## Production Gaps (Demo Limitations)

`SettlementCalculator.getSettlementAmounts()` currently sends 100% to the merchant wallet. Operator fee is recorded as `PENDING` only. Production requires chain-specific split logic:
- ETH: Splitter smart contract
- BTC: PSBT multi-output  
- XRP: Payment Channel / Hooks

Etherscan (ETH + USDT ERC-20) and BscScan (USDT BEP-20) default to the placeholder key `"YourApiKeyToken"` in `BlockchainClients.kt` — replace with real keys for production. TronGrid (USDT TRC-20) works without a key for basic polling.

## Code Comments

All code comments must be written in English. When porting/migrating code from
another source (e.g. the parallel `minapay` repo), translate any Japanese
comments to English rather than copying them verbatim. UI strings that are
intentionally localized (e.g. the `"日本語"` language-switcher label) are not
comments and should be left as-is.

## Git Commit Messages

Do not include a `Co-Authored-By` trailer in commit messages.

## Send Demo APK

1. Build the debug APK: `./gradlew assembleDebug`
2. Create a new folder named with the current date and time (e.g. `2026-07-02_1430`) inside `/Users/thanhphat/Library/CloudStorage/GoogleDrive-admin@lybiasoft.com/My Drive/Demo/KG/mina_pos`
3. Copy `app/build/outputs/apk/debug/app-debug.apk` into that new folder