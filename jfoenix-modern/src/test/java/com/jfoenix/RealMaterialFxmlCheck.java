package com.jfoenix;

import com.jfoenix.controls.JFXColorPicker;
import com.jfoenix.controls.JFXSlider;
import com.jfoenix.controls.JFXTimePicker;
import javafx.application.Platform;
import javafx.fxml.FXMLLoader;
import javafx.scene.Node;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.Control;
import javafx.stage.PopupWindow;
import javafx.stage.Stage;
import javafx.stage.Window;

import java.nio.file.Files;
import java.nio.file.Path;
import java.util.ArrayList;
import java.util.List;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.FutureTask;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicInteger;
import java.util.concurrent.atomic.AtomicReference;
import javax.xml.stream.XMLInputFactory;
import javax.xml.stream.XMLStreamConstants;
import javax.xml.stream.XMLStreamReader;

/** Loads the user's copied all-material FXML unchanged, then exercises its actual controls. */
public final class RealMaterialFxmlCheck {
    private static final AtomicReference<Throwable> uncaught = new AtomicReference<>();
    private static Stage stage;
    private static Parent root;

    private static void require(boolean condition, String message) {
        if (!condition) throw new AssertionError(message);
    }

    private static void fx(Runnable action) throws Exception {
        FutureTask<Void> task = new FutureTask<>(() -> { action.run(); return null; });
        Platform.runLater(task);
        task.get(20, TimeUnit.SECONDS);
        if (uncaught.get() != null) throw new AssertionError("Uncaught JavaFX failure", uncaught.get());
    }

    private static void pulse() throws Exception {
        Thread.sleep(250);
        fx(() -> { root.applyCss(); root.layout(); });
    }

    private static List<Node> descendants(Node node) {
        List<Node> result = new ArrayList<>();
        result.add(node);
        if (node instanceof Parent) for (Node child : ((Parent) node).getChildrenUnmodifiable()) result.addAll(descendants(child));
        return result;
    }

    private static List<Window> popups() {
        List<Window> result = new ArrayList<>();
        for (Window window : Window.getWindows()) if (window instanceof PopupWindow && window.isShowing() && ((PopupWindow) window).getOwnerWindow() == stage) result.add(window);
        return result;
    }

    private static double sliderSourceValue(Path fxml) throws Exception {
        XMLInputFactory factory = XMLInputFactory.newFactory();
        factory.setProperty(XMLInputFactory.SUPPORT_DTD, false);
        factory.setProperty("javax.xml.stream.isSupportingExternalEntities", false);
        try (java.io.InputStream source = Files.newInputStream(fxml)) {
            XMLStreamReader xml = factory.createXMLStreamReader(source);
            try {
                while (xml.hasNext()) if (xml.next() == XMLStreamConstants.START_ELEMENT && xml.getLocalName().endsWith("JFXSlider") && "slider".equals(xml.getAttributeValue(null, "id"))) {
                    return Double.parseDouble(xml.getAttributeValue(null, "value"));
                }
            } finally { xml.close(); }
        }
        throw new AssertionError("Real FXML slider value is missing");
    }

    public static void main(String[] args) throws Exception {
        require(args.length == 1, "Pass the copied ozStudio ttest.fxml path");
        Path fxml = Path.of(args[0]).toAbsolutePath();
        double originalSliderValue = sliderSourceValue(fxml);
        Thread.UncaughtExceptionHandler previous = Thread.getDefaultUncaughtExceptionHandler();
        Thread.setDefaultUncaughtExceptionHandler((thread, failure) -> uncaught.compareAndSet(null, failure));
        CountDownLatch ready = new CountDownLatch(1);
        Platform.startup(() -> {
            Platform.setImplicitExit(false);
            Thread.currentThread().setUncaughtExceptionHandler((thread, failure) -> uncaught.compareAndSet(null, failure));
            ready.countDown();
        });
        require(ready.await(15, TimeUnit.SECONDS), "JavaFX startup timed out");
        AtomicReference<List<JFXColorPicker>> colors = new AtomicReference<>();
        AtomicReference<JFXTimePicker> time = new AtomicReference<>();
        AtomicReference<JFXSlider> slider = new AtomicReference<>();
        try {
            fx(() -> {
                try { root = FXMLLoader.load(fxml.toUri().toURL()); }
                catch (Exception failure) { throw new AssertionError("Real material FXML failed to load", failure); }
                List<Node> authored = descendants(root);
                colors.set(authored.stream().filter(node -> node instanceof JFXColorPicker).map(node -> (JFXColorPicker) node).toList());
                time.set(authored.stream().filter(node -> node instanceof JFXTimePicker && "timeEdit".equals(node.getId())).map(node -> (JFXTimePicker) node).findFirst().orElseThrow());
                slider.set(authored.stream().filter(node -> node instanceof JFXSlider && "slider".equals(node.getId())).map(node -> (JFXSlider) node).findFirst().orElseThrow());
                require(colors.get().size() >= 2, "Real FXML color pickers are missing");
                stage = new Stage(); stage.setScene(new Scene(root)); stage.show();
                root.applyCss(); root.layout();
                long materialCount = authored.stream().filter(node -> node instanceof Control && node.getClass().getName().startsWith("com.jfoenix.controls.")).count();
                require(materialCount >= 10, "All-material fixture has too few controls: " + materialCount);
                for (Node node : authored) if (node instanceof Control && node.getClass().getName().startsWith("com.jfoenix.controls.")) {
                    require(((Control) node).getSkin() != null, "Real FXML skin missing: " + node.getId());
                }
                require(Math.abs(slider.get().getValue() - originalSliderValue) < 0.000001, "FXML slider value changed during skin creation");
            });
            pulse(); pulse(); pulse();
            fx(() -> {
                JFXSlider control = slider.get();
                require(Math.abs(control.getValue() - originalSliderValue) < 0.000001, "FXML slider value changed after pulses");
                AtomicInteger changes = new AtomicInteger();
                javafx.beans.value.ChangeListener<Number> listener = (property, oldValue, newValue) -> changes.incrementAndGet();
                control.valueProperty().addListener(listener);
                try {
                    double next = control.getMin() + (control.getMax() - control.getMin()) / 2;
                    if (Math.abs(next - originalSliderValue) < 0.01) next = control.getMin();
                    control.setValue(next);
                    require(Math.abs(control.getValue() - next) < 0.000001 && changes.get() == 1, "Real slider value/listener failed");
                    control.setValue(originalSliderValue);
                    require(changes.get() == 2, "Real slider restore/listener failed");
                } finally { control.valueProperty().removeListener(listener); }
                time.get().show();
            });
            pulse();
            fx(() -> {
                require(time.get().isShowing() && popups().stream().anyMatch(window -> window.getScene().getRoot().lookup(".time-pane") != null), "Real timeEdit clock popup is missing");
                time.get().hide();
            });
            pulse(); fx(() -> require(popups().isEmpty(), "Real clock popup survived hide"));
            for (JFXColorPicker control : colors.get()) {
                fx(control::show); pulse();
                fx(() -> {
                    require(control.isShowing() && popups().stream().anyMatch(window -> window.getScene().getRoot().lookup(".color-palette-region") != null), "Real color popup is missing: " + control.getId());
                    control.hide();
                });
                pulse(); fx(() -> require(popups().isEmpty(), "Real color popup survived hide: " + control.getId()));
            }
            fx(() -> require(Math.abs(slider.get().getValue() - originalSliderValue) < 0.000001, "Real slider value lost after picker use"));
            System.out.println("PASS real ozStudio material FXML: all controls loaded/skinned, timeEdit clock + both color popups, slider source value + runtime listener preserved; no uncaught FX errors.");
        } finally {
            try { fx(() -> { if (stage != null) stage.close(); }); }
            finally { Platform.exit(); Thread.setDefaultUncaughtExceptionHandler(previous); }
        }
    }
}
