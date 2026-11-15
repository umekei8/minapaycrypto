package com.minapay.pos.util

import android.content.Context
import android.content.SharedPreferences
import android.content.res.Configuration
import java.util.Locale

/**
 * App language is an explicit user choice, independent of the device locale.
 * Defaults to Japanese; switching to English is a manual action in Settings.
 */
object LocaleManager {
    const val JAPANESE = "ja"
    const val ENGLISH = "en"

    private const val PREFS_NAME = "minapay_prefs"
    private const val KEY_LANGUAGE = "app_language"

    private fun prefs(context: Context): SharedPreferences =
        context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    fun getLanguage(context: Context): String =
        prefs(context).getString(KEY_LANGUAGE, JAPANESE) ?: JAPANESE

    fun setLanguage(context: Context, language: String) {
        prefs(context).edit().putString(KEY_LANGUAGE, language).apply()
    }

    /** Returns a Context whose resources resolve to the saved app language. */
    fun wrap(context: Context): Context {
        val locale = Locale.Builder().setLanguage(getLanguage(context)).build()
        val config = Configuration(context.resources.configuration)
        config.setLocale(locale)
        return context.createConfigurationContext(config)
    }
}
