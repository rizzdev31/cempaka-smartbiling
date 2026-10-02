plugins {
    alias(libs.plugins.android.application)
    alias(libs.plugins.kotlin.android)
}

android {
    namespace = "id.cempaka.tvagent"
    compileSdk = 35

    defaultConfig {
        applicationId = "id.cempaka.tvagent"

        // Android 6. TV di pasaran Indonesia banyak yang masih Android 9–11;
        // menaikkan minSdk tidak memberi manfaat dan memotong perangkat.
        minSdk = 23

        // Sengaja 34, bukan 35. Android 35 memaksa edge-to-edge yang tidak
        // relevan di TV dan menyulitkan layout overscan.
        targetSdk = 34

        versionCode = 1
        versionName = "0.1.0"
    }

    buildTypes {
        debug {
            isMinifyEnabled = false
            applicationIdSuffix = ".debug"
            versionNameSuffix = "-debug"
        }
        release {
            isMinifyEnabled = true
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro",
            )
            // Belum ada signing config. Release build dipakai nanti saat
            // perangkat dan prosedur deploy sudah ditetapkan (OD-005).
            signingConfig = signingConfigs.getByName("debug")
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = "17"
    }

    buildFeatures {
        buildConfig = true
    }

    packaging {
        resources.excludes += setOf("META-INF/*.kotlin_module")
    }
}

dependencies {
    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.appcompat)
    implementation(libs.androidx.activity)
    implementation(libs.androidx.lifecycle.service)
    implementation(libs.androidx.lifecycle.runtime.ktx)
    implementation(libs.kotlinx.coroutines.android)

    // Server HTTP untuk kontrol langsung operator -> TV.
    //
    // NanoHTTPD dipilih walau sudah lama tidak di-update: cakupannya kecil
    // (lima endpoint, body JSON kecil, hanya LAN), teruji luas, dan akan
    // DIGANTI klien Reverb di Tahap 2 (DEC-015). Menulis parser HTTP sendiri
    // untuk ini mengundang bug keep-alive dan chunked encoding tanpa manfaat.
    implementation(libs.nanohttpd)

    testImplementation(libs.junit)
}
