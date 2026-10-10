package com.jfoenix;

import com.jfoenix.controls.JFXSpinner;
import javafx.application.Platform;
import javafx.scene.Scene;
import javafx.scene.control.Skin;
import javafx.scene.layout.StackPane;
import javafx.scene.shape.Arc;
import javafx.stage.Stage;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.FutureTask;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicReference;

/** Real CSS/layout/pulses without module exports, on an explicitly isolated native QA desktop. */
public final class SpinnerSkinCheck {
    private static final AtomicReference<Throwable> failure = new AtomicReference<>();
    private static Stage stage;
    private static StackPane root, parent;
    private static JFXSpinner spinner;
    private static Arc arc;
    private static void require(boolean value, String message) { if (!value) throw new AssertionError(message); }
    private static void fx(Runnable action) throws Exception {
        FutureTask<Void> task = new FutureTask<>(() -> { action.run(); return null; });
        Platform.runLater(task); task.get(10, TimeUnit.SECONDS);
        if (failure.get() != null) throw new AssertionError("Uncaught FX error", failure.get());
    }
    private static double[] position() { return new double[]{arc.getStartAngle(), arc.getLength()}; }
    private static void animation(boolean expected, String context) throws Exception {
        AtomicReference<double[]> before = new AtomicReference<>(), after = new AtomicReference<>();
        fx(() -> { root.applyCss(); root.layout(); before.set(position()); });
        Thread.sleep(320);
        fx(() -> after.set(position()));
        boolean moved = Math.abs(before.get()[0] - after.get()[0]) > 0.001 || Math.abs(before.get()[1] - after.get()[1]) > 0.001;
        require(moved == expected, "Spinner animation state: " + context);
    }
    public static void main(String[] args) throws Exception {
        require(System.getenv("DEVLINE_NATIVE_QA_DESKTOP") != null && System.getenv("DEVLINE_NATIVE_QA_DESKTOP").startsWith("DevLine-QA-"),
            "Use tools/native-qa-desktop.ps1 for this native check");
        Thread.setDefaultUncaughtExceptionHandler((thread, error) -> { failure.compareAndSet(null, error); error.printStackTrace(System.err); });
        CountDownLatch started = new CountDownLatch(1); Platform.startup(() -> { Platform.setImplicitExit(false); started.countDown(); });
        require(started.await(10, TimeUnit.SECONDS), "FX startup timed out");
        try {
            fx(() -> {
                spinner = new JFXSpinner(); spinner.setPrefSize(80, 80);
                parent = new StackPane(spinner); root = new StackPane(parent);
                stage = new Stage(); stage.setTitle("DevLine hidden spinner QA");
                stage.setScene(new Scene(root, 160, 160)); stage.show(); root.applyCss(); root.layout();
                require(spinner.getSkin() != null, "Spinner skin missing");
                arc = (Arc) spinner.lookup(".arc"); require(arc != null && arc.getRadiusX() > 0, "Spinner arc missing geometry");
            });
            animation(true, "shown");
            fx(() -> spinner.setVisible(false)); animation(false, "control hidden");
            fx(() -> spinner.setVisible(true)); animation(true, "control shown");
            fx(() -> parent.setVisible(false)); animation(false, "ancestor hidden");
            fx(() -> parent.setVisible(true)); animation(true, "ancestor shown");
            fx(() -> stage.hide()); animation(false, "window hidden");
            fx(() -> stage.show()); animation(true, "window shown");
            fx(() -> parent.getChildren().remove(spinner)); animation(false, "detached");
            fx(() -> parent.getChildren().add(spinner)); animation(true, "reattached");
            fx(() -> { spinner.setProgress(0.4); root.applyCss(); root.layout(); require(Math.abs(arc.getLength() + 144) < 0.001, "Determinate progress arc is wrong"); });
            animation(false, "determinate");
            fx(() -> spinner.setProgress(-1)); animation(true, "indeterminate again");
            fx(() -> { Skin<?> old = spinner.getSkin(); spinner.setSkin(null); old.dispose(); old.dispose(); });
            animation(false, "disposed old arc");
            fx(() -> { parent.setVisible(false); stage.hide(); parent.setVisible(true); stage.show(); });
            animation(false, "disposed observers cannot restart old arc");
            System.out.println("JAVAFX25_SPINNER_GEOMETRY_PROGRESS_VISIBILITY_ANCESTOR_WINDOW_DETACH_AND_DISPOSAL_NO_EXPORTS_PASS");
        } finally {
            try { fx(() -> { if (stage != null) stage.close(); }); } finally { Platform.exit(); }
        }
    }
}
