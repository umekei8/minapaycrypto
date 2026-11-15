package com.minapay.pos.util

import android.content.Context
import com.google.gson.Gson
import com.minapay.pos.data.model.DemoConfig
import java.io.File

object ConfigLoader {
    private var cached: DemoConfig? = null

    fun load(context: Context): DemoConfig {
        cached?.let { return it }
        return try {
            // 1. Prefer the file saved from the settings screen
            val savedFile = File(context.filesDir, "demo_config.json")
            val json = if (savedFile.exists()) {
                savedFile.readText()
            } else {
                // 2. Fall back to the bundled default in assets
                context.assets.open("config/demo_config.json")
                    .bufferedReader().use { it.readText() }
            }
            Gson().fromJson(json, DemoConfig::class.java).also { cached = it }
        } catch (e: Exception) {
            DemoConfig().also { cached = it }
        }
    }

    /** Persist config to internal storage and refresh the cache. */
    fun save(context: Context, config: DemoConfig) {
        runCatching {
            File(context.filesDir, "demo_config.json").writeText(Gson().toJson(config))
        }
        cached = config
    }

    fun invalidate() { cached = null }
}
