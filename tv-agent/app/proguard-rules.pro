# NanoHTTPD memakai refleksi minim; aman tapi kelas publiknya dipertahankan.
-keep class org.nanohttpd.** { *; }
-dontwarn org.nanohttpd.**
