# JFoenix for JavaFX 25

Source repairs derived from JFoenix 9.0.10 (Apache-2.0); original source headers are retained. Gradle resolves the immutable upstream binary and source coordinates. The output is one JAR, with repaired classes compiled from the checked-in Java sources and unchanged classes/resources from upstream. It replaces upstream JFoenix in both legacy and DevLine distributions.

Repairs use public JavaFX skin, popup and scene/window lifecycle APIs. DatePicker exposes a final, read-only editor API; its public editor text is synchronized with an owned Material editor instead of changing private fields. Floating labels, validation, native text selection/masking, calendar/time/color popup contents and overlay dialogs remain enabled.

Run `modernSkinsCheck` with JDK 25. No module opens or exports are added for these repairs.

Validated on JDK/JavaFX 25.0.4: Material fields/password/textarea labels and validation; date/time keyboard, empty input, blur, calendar/clock popup and overlay; owner move/hide; color popup; progress visibility/window/detach/dispose. The copied real ozStudio `ttest.fxml` loads and skins all authored controls. A modern canonical form additionally runs through the actual PHP/native compiler, UXLoader, shown CSS/layout and Stop (including TextAreaFixed/TabPaneFixed wrappers).

The package resolver selects this host-tested artifact only for the exact upstream 9.0.10 binary or this artifact. Installed package originals and locks remain unchanged; unknown custom variants require explicit review. Existing legacy exports used by other controls remain separate debt. `java.base/java.lang.reflect` no longer needs an open for JFoenix. Restart an already running IDE to load the new dependency; no running IDE is restarted by the build task.
