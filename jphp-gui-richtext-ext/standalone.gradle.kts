plugins { `java-library` }
group = "org.develnext.jphp"
version = "0.9.3-SNAPSHOT"
java { toolchain.languageVersion.set(JavaLanguageVersion.of(25)) }
dependencies {
    implementation("org.develnext.jphp:jphp-runtime:0.9.3-SNAPSHOT")
    implementation("org.develnext.jphp:jphp-gui-ext:0.9.3-SNAPSHOT") { isTransitive = false }
    implementation("org.fxmisc.richtext:richtextfx:0.11.7")
    val platform = if (System.getProperty("os.name").startsWith("Windows")) "win" else if (System.getProperty("os.name").contains("Mac")) "mac" else "linux"
    for (module in listOf("base", "graphics", "controls")) compileOnly("org.openjfx:javafx-$module:25.0.4:$platform") { isTransitive = false }
}

 tasks.jar {
    from(projectDir.parentFile.resolve("LICENSE")) { into("META-INF/licenses"); rename { "DevelNext-MPL-2.0.txt" } }
    manifest.attributes["DevLine-JavaFX-Target"] = "25"
}
