package com.minapay.pos.util

import com.minapay.pos.data.model.CryptoCurrency

fun explorerUrl(currency: CryptoCurrency, address: String): String = when (currency) {
    CryptoCurrency.BTC  -> "https://mempool.space/address/$address"
    CryptoCurrency.ETH  -> "https://etherscan.io/address/$address"
    CryptoCurrency.SOL  -> "https://solscan.io/account/$address"
    CryptoCurrency.XRP  -> "https://livenet.xrpl.org/accounts/$address"
    CryptoCurrency.JPYC -> "https://etherscan.io/address/$address"
}
