plugins {
    id("com.android.application")
    id("com.google.devtools.ksp")
    id("org.jetbrains.kotlin.plugin.compose")
}

android {
    namespace = "com.minapay.pos"
    compileSdk = 37

    defaultConfig {
        applicationId = "com.minapay.pos"
        minSdk = 29
        targetSdk = 37
        versionCode = 10
        versionName = "1.0.10"
    }

    buildTypes {
        release {
            isMinifyEnabled = false
        }
        debug {
            isDebuggable = true
        }
    }

    buildFeatures {
        compose = true
        buildConfig = true
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    // ZCS SDK AARs in libs/
    sourceSets {
        getByName("main") {
            jniLibs.directories.add("libs")
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget.set(org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17)
    }
}

dependencies {
    // ZCS SmartPos SDK — place AARs and JARs in app/libs/
    // SmartPos_2.0.4_R260318.aar, emv_2.0.2_R260309.aar, core-3.2.1.jar
    // core-3.2.1.jar is excluded: it bundles an older com.google.zxing:core that
    // duplicates classes with the explicit com.google.zxing:core:3.5.4 dependency
    // below (used by QrGenerator). The app never calls SmartPos's zxing-referencing
    // APIs directly — everything goes through reflection — so this is safe to drop.
    implementation(fileTree(mapOf("dir" to "libs", "include" to listOf("*.aar"))))

    // Compose BOM
    val composeBom = platform("androidx.compose:compose-bom:2026.04.01")
    implementation(composeBom)
    implementation("androidx.compose.ui:ui")
    implementation("androidx.compose.ui:ui-tooling-preview")
    implementation("androidx.compose.material3:material3")
    implementation("androidx.compose.material:material-icons-extended")
    implementation("androidx.activity:activity-compose:1.13.0")
    implementation("androidx.lifecycle:lifecycle-viewmodel-compose:2.11.0")
    implementation("androidx.lifecycle:lifecycle-runtime-ktx:2.11.0")
    implementation("androidx.navigation:navigation-compose:2.9.8")

    // Network
    implementation("com.squareup.retrofit2:retrofit:3.0.0")
    implementation("com.squareup.retrofit2:converter-gson:3.0.0")
    implementation("com.squareup.okhttp3:okhttp:5.4.0")
    implementation("com.squareup.okhttp3:logging-interceptor:5.4.0")
    implementation("com.google.code.gson:gson:2.14.0")

    // Room
    implementation("androidx.room:room-runtime:2.8.4")
    implementation("androidx.room:room-ktx:2.8.4")
    ksp("androidx.room:room-compiler:2.8.4")

    // QR Code generation
    implementation("com.google.zxing:core:3.5.4")

    // CameraX + ML Kit Barcode Scanner (ウォレットアドレスQRスキャン用)
    implementation("androidx.camera:camera-camera2:1.4.2")
    implementation("androidx.camera:camera-lifecycle:1.4.2")
    implementation("androidx.camera:camera-view:1.4.2")
    implementation("com.google.mlkit:barcode-scanning:17.3.0")

    // Coroutines
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.11.0")

    // DataStore (config)
    implementation("androidx.datastore:datastore-preferences:1.2.1")

    debugImplementation("androidx.compose.ui:ui-tooling")
}
