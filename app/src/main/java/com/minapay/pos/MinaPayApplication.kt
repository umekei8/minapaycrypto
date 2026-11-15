package com.minapay.pos

import android.app.Application
import android.content.Context
import com.minapay.pos.data.db.MinaPayDatabase
import com.minapay.pos.util.LocaleManager

class MinaPayApplication : Application() {

    val database: MinaPayDatabase by lazy {
        MinaPayDatabase.getInstance(this)
    }

    override fun attachBaseContext(base: Context) {
        super.attachBaseContext(LocaleManager.wrap(base))
    }

    override fun onCreate() {
        super.onCreate()
        // ZCS SDK init via reflection (runs only on actual ZCS device)
        initZcsSdk()
    }

    private fun initZcsSdk() {
        try {
            val dmClass = Class.forName("com.zcs.sdk.DriverManager")
            val dm = dmClass.getMethod("getInstance").invoke(null)
            val sys = dmClass.getMethod("getBaseSysDevice").invoke(dm)
            val sdkResult = Class.forName("com.zcs.sdk.SdkResult")
            val sdkOk = sdkResult.getField("SDK_OK").getInt(null)

            var status = sys!!.javaClass.getMethod("sdkInit").invoke(sys) as Int
            if (status != sdkOk) {
                sys.javaClass.getMethod("sysPowerOn").invoke(sys)
                Thread.sleep(1000)
                status = sys.javaClass.getMethod("sdkInit").invoke(sys) as Int
            }
            // Enable debug log in debug builds
            if (BuildConfig.DEBUG) {
                sys.javaClass.getMethod("showDetailLog", Boolean::class.java).invoke(sys, true)
            }
        } catch (e: Exception) {
            // Not a ZCS device — ignore (emulator / development)
        }
    }
}
