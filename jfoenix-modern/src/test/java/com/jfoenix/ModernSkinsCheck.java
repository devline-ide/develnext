package com.jfoenix;

import com.jfoenix.controls.*;
import com.jfoenix.validation.RequiredFieldValidator;
import javafx.application.Platform;
import javafx.event.Event;
import javafx.scene.Node;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.Control;
import javafx.scene.control.Skin;
import javafx.scene.control.TextField;
import javafx.scene.input.KeyCode;
import javafx.scene.input.KeyEvent;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;
import javafx.scene.text.Text;
import javafx.stage.PopupWindow;
import javafx.stage.Stage;
import javafx.stage.Window;

import java.time.LocalDate;
import java.time.LocalTime;
import java.util.ArrayList;
import java.util.List;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.FutureTask;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicReference;
import java.util.function.BooleanSupplier;

/** Runs real JavaFX skins and pulses; no reflective/private skin access or JVM opens are required. */
public final class ModernSkinsCheck {
    private static final AtomicReference<Throwable> uncaught = new AtomicReference<>();
    private static Stage stage;
    private static StackPane root;
    private static StackPane progressParent;
    private static JFXTextField field;
    private static JFXPasswordField password;
    private static JFXTextArea area;
    private static JFXDatePicker date;
    private static JFXTimePicker time;
    private static JFXColorPicker color;
    private static JFXProgressBar progress;

    private static void require(boolean condition, String message) {
        if (!condition) throw new AssertionError(message);
    }

    private static void fx(Runnable action) throws Exception {
        FutureTask<Void> task = new FutureTask<>(() -> { action.run(); return null; });
        Platform.runLater(task);
        task.get(15, TimeUnit.SECONDS);
        if (uncaught.get() != null) throw new AssertionError("Uncaught JavaFX failure", uncaught.get());
    }

    private static void pulse() throws Exception {
        // Wait outside the FX thread, so animations and event handlers actually execute.
        Thread.sleep(260);
        fx(() -> { if (root != null) { root.applyCss(); root.layout(); } });
    }

    private static void await(BooleanSupplier condition, String message) throws Exception {
        long deadline = System.nanoTime() + TimeUnit.SECONDS.toNanos(5);
        AtomicReference<Boolean> done = new AtomicReference<>(false);
        do {
            fx(() -> done.set(condition.getAsBoolean()));
            if (done.get()) return;
            Thread.sleep(50);
        } while (System.nanoTime() < deadline);
        throw new AssertionError(message);
    }

    private static List<Node> descendants(Node node) {
        List<Node> result = new ArrayList<>();
        result.add(node);
        if (node instanceof Parent) for (Node child : ((Parent) node).getChildrenUnmodifiable()) result.addAll(descendants(child));
        return result;
    }

    private static Text floatingPrompt(Control control, String label) {
        Node container = control.lookup(".prompt-container");
        require(container != null, "Missing floating prompt container: " + label);
        return descendants(container).stream().filter(node -> node instanceof Text && label.equals(((Text) node).getText()))
            .map(node -> (Text) node).findFirst().orElseThrow(() -> new AssertionError("Missing floating prompt: " + label));
    }

    private static List<Window> popups() {
        List<Window> result = new ArrayList<>();
        for (Window window : Window.getWindows()) if (window instanceof PopupWindow && window.isShowing() && ((PopupWindow) window).getOwnerWindow() == stage) result.add(window);
        return result;
    }

    private static void popup(String selector) {
        require(popups().stream().anyMatch(window -> window.getScene().getRoot().lookup(selector) != null), "Popup content missing: " + selector);
    }

    private static void enter(Control control) {
        key(control, KeyCode.ENTER);
    }

    private static void key(Control control, KeyCode code) {
        Event.fireEvent(control, new KeyEvent(KeyEvent.KEY_PRESSED, "", "", code, false, false, false, false));
    }

    private static double[] animationPosition() {
        Node bar = progress.lookup(".bar");
        require(bar != null && bar.getClip() != null, "Indeterminate progress clip missing");
        return new double[]{bar.getClip().getTranslateX(), bar.getClip().getScaleX()};
    }

    private static boolean same(double[] a, double[] b) {
        return Math.abs(a[0] - b[0]) < 0.00001 && Math.abs(a[1] - b[1]) < 0.00001;
    }

    private static void animation(boolean moving, String state) throws Exception {
        AtomicReference<double[]> before = new AtomicReference<>();
        fx(() -> before.set(animationPosition()));
        pulse();
        fx(() -> require(same(before.get(), animationPosition()) != moving, "Progress animation wrong while " + state));
    }

    public static void main(String[] args) throws Exception {
        Thread.UncaughtExceptionHandler previous = Thread.getDefaultUncaughtExceptionHandler();
        Thread.setDefaultUncaughtExceptionHandler((thread, failure) -> uncaught.compareAndSet(null, failure));
        CountDownLatch ready = new CountDownLatch(1);
        Platform.startup(() -> {
            Platform.setImplicitExit(false);
            Thread.currentThread().setUncaughtExceptionHandler((thread, failure) -> uncaught.compareAndSet(null, failure));
            ready.countDown();
        });
        require(ready.await(15, TimeUnit.SECONDS), "JavaFX startup timed out");
        try {
            fx(() -> {
                field = new JFXTextField(); field.setPromptText("Field"); field.setLabelFloat(true);
                password = new JFXPasswordField(); password.setPromptText("Password"); password.setLabelFloat(true);
                area = new JFXTextArea(); area.setPromptText("Area"); area.setLabelFloat(true); area.setPrefRowCount(3);
                field.setValidators(new RequiredFieldValidator("Required"));
                password.setValidators(new RequiredFieldValidator("Required"));
                area.setValidators(new RequiredFieldValidator("Required"));
                date = new JFXDatePicker(); date.setEditable(true);
                time = new JFXTimePicker(); time.setEditable(true);
                color = new JFXColorPicker();
                progress = new JFXProgressBar(0.5); progress.setPrefWidth(320);
                progressParent = new StackPane(progress);
                VBox controls = new VBox(18, field, password, area, date, time, color, progressParent);
                controls.setPrefWidth(360);
                root = new StackPane(controls); root.setPrefSize(500, 640);
                date.setDialogParent(root); time.setDialogParent(root);
                stage = new Stage(); stage.setScene(new Scene(root)); stage.show();
                root.applyCss(); root.layout();
                for (Control control : List.of(field, password, area, date, time, color, progress)) require(control.getSkin() != null, "Skin missing: " + control.getClass().getSimpleName());
                require(!field.validate() && field.getActiveValidator() != null, "Text field validation failed");
                require(!password.validate() && password.getActiveValidator() != null, "Password validation failed");
                require(!area.validate() && area.getActiveValidator() != null, "Text area validation failed");
                field.setText("value"); password.setText("secret-value"); area.setText("line one\nline two");
                require(field.validate() && password.validate() && area.validate(), "Validation did not clear on valid input");
                date.requestFocus();
            });
            pulse();
            fx(() -> {
                for (Control control : List.of(field, password, area)) {
                    String label = control == field ? "Field" : control == password ? "Password" : "Area";
                    Text prompt = floatingPrompt(control, label);
                    require(prompt.isVisible() && prompt.getOpacity() > 0 && prompt.getTranslateY() < 0, "Filled input label did not float: " + label);
                }
                require(descendants(password).stream().noneMatch(node -> node instanceof Text && "secret-value".equals(((Text) node).getText())), "Password value rendered without masking");
                long visibleAreaPrompts = descendants(area).stream().filter(node -> node instanceof Text && "Area".equals(((Text) node).getText()) && node.isVisible() && node.getOpacity() > 0).count();
                require(visibleAreaPrompts == 1, "TextArea shows duplicate prompts");
                LocalDate value = LocalDate.of(2026, 10, 7);
                date.getEditor().setText(date.getConverter().toString(value)); enter(date);
                require(value.equals(date.getValue()), "Editable DatePicker did not commit public editor text");
                LocalTime clock = LocalTime.of(13, 35);
                time.getEditor().setText(time.getConverter().toString(clock)); enter(time);
                require(clock.equals(time.getValue()), "Editable TimePicker did not commit editor text");
                date.setOverLay(false); date.show();
            });
            pulse(); fx(() -> { require(date.isShowing(), "Calendar showing state missing"); popup(".calendar-grid"); });
            AtomicReference<Double> calendarX = new AtomicReference<>();
            fx(() -> { calendarX.set(popups().get(0).getX()); stage.setX(stage.getX() + 24); });
            pulse();
            fx(() -> { require(Math.abs(popups().get(0).getX() - calendarX.get() - 24) < 2, "Calendar popup did not follow owner movement"); date.hide(); });
            pulse(); fx(() -> {
                require(popups().isEmpty(), "Calendar popup survived hide");
                date.setEditable(false); key(date, KeyCode.SPACE);
            });
            pulse(); fx(() -> { require(date.isShowing(), "Read-only DatePicker SPACE did not open"); popup(".calendar-grid"); key(date, KeyCode.ESCAPE); });
            pulse(); fx(() -> {
                require(!date.isShowing() && popups().isEmpty(), "Read-only DatePicker ESCAPE did not close");
                key(date, KeyCode.ENTER);
                require(date.isShowing(), "Read-only DatePicker ENTER did not open");
                key(date, KeyCode.ESCAPE);
                date.setEditable(true); date.getEditor().clear(); enter(date);
                require(date.getValue() == null, "Empty DatePicker editor did not commit null");
                time.getEditor().clear(); enter(time);
                require(time.getValue() == null, "Empty TimePicker editor did not commit null");
                date.setValue(LocalDate.of(2026, 10, 7));
                stage.requestFocus(); ((TextField) date.lookup(".date-picker-display-node")).requestFocus();
            });
            await(() -> ((TextField) date.lookup(".date-picker-display-node")).isFocused(), "DatePicker material editor could not focus");
            fx(() -> { date.getEditor().clear(); field.requestFocus(); });
            await(() -> field.isFocused() && date.getValue() == null, "Empty DatePicker editor did not commit on blur");
            fx(() -> { time.setValue(LocalTime.of(13, 35)); time.getEditor().requestFocus(); });
            await(() -> time.getEditor().isFocused(), "TimePicker editor could not focus");
            fx(() -> { time.getEditor().clear(); field.requestFocus(); });
            await(() -> field.isFocused() && time.getValue() == null, "Empty TimePicker editor did not commit on blur");
            fx(() -> { time.setOverLay(false); time.show(); });
            pulse(); fx(() -> { require(time.isShowing(), "Clock showing state missing"); popup(".time-pane"); time.hide(); });
            pulse(); fx(() -> { require(popups().isEmpty(), "Clock popup survived hide"); color.show(); });
            pulse(); fx(() -> { require(color.isShowing(), "Color popup showing state missing"); popup(".color-palette-region"); color.hide(); });
            pulse(); fx(() -> { require(popups().isEmpty(), "Color popup survived hide"); date.show(); });
            pulse(); fx(() -> stage.hide());
            await(() -> !date.isShowing() && popups().isEmpty(), "Calendar popup survived owner hide");
            fx(() -> { stage.show(); date.setOverLay(true); date.show(); });
            pulse(); fx(() -> { require(root.lookup(".jfx-dialog") != null && root.lookup(".calendar-grid") != null, "Calendar overlay content missing"); date.hide(); });
            await(() -> root.lookup(".jfx-dialog") == null, "Calendar overlay survived hide");
            fx(() -> { time.setOverLay(true); time.show(); });
            pulse(); fx(() -> { require(root.lookup(".jfx-dialog") != null && root.lookup(".time-pane") != null, "Clock overlay content missing"); time.hide(); });
            await(() -> root.lookup(".jfx-dialog") == null, "Clock overlay survived hide");
            fx(() -> {
                Node bar = progress.lookup(".bar"); require(bar != null && bar.getBoundsInParent().getWidth() > 0, "Determinate progress has no bar");
                require(bar.getClip() == null, "Determinate progress unexpectedly clipped");
                progress.setProgress(-1);
            });
            pulse(); animation(true, "visible");
            fx(() -> progressParent.setVisible(false)); animation(false, "ancestor hidden");
            fx(() -> progressParent.setVisible(true)); animation(true, "ancestor visible again");
            fx(() -> stage.hide()); animation(false, "window hidden");
            fx(() -> stage.show()); pulse(); animation(true, "window shown again");
            fx(() -> progressParent.getChildren().remove(progress)); animation(false, "detached from scene");
            fx(() -> progressParent.getChildren().add(progress)); pulse(); animation(true, "reattached");
            AtomicReference<Node> disposedClip = new AtomicReference<>();
            AtomicReference<double[]> disposedPosition = new AtomicReference<>();
            fx(() -> {
                disposedClip.set(progress.lookup(".bar").getClip());
                Skin<?> old = progress.getSkin(); progress.setSkin(null); old.dispose();
                disposedPosition.set(new double[]{disposedClip.get().getTranslateX(), disposedClip.get().getScaleX()});
                progressParent.setVisible(false); stage.hide();
            });
            pulse();
            fx(() -> { progressParent.setVisible(true); stage.show(); });
            pulse();
            fx(() -> require(same(disposedPosition.get(), new double[]{disposedClip.get().getTranslateX(), disposedClip.get().getScaleX()}), "Disposed progress animation resumed"));
            System.out.println("PASS JavaFX25 material skins: fields/password/area labels+validation, date/time commit+blur+keyboard+popup+overlay, owner movement/hide, color popup, progress visibility/window/detach/dispose lifecycle; no uncaught FX errors.");
        } finally {
            try { fx(() -> { for (Window window : new ArrayList<>(Window.getWindows())) window.hide(); }); }
            finally { Platform.exit(); Thread.setDefaultUncaughtExceptionHandler(previous); }
        }
    }
}
