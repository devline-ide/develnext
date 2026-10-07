/*
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

package com.jfoenix.skins;

import javafx.event.EventHandler;
import javafx.css.Styleable;
import javafx.geometry.Bounds;
import javafx.scene.Node;
import javafx.scene.control.ComboBoxBase;
import javafx.scene.control.ColorPicker;
import javafx.scene.control.PopupControl;
import javafx.scene.control.Skin;
import javafx.scene.control.SkinBase;
import javafx.scene.control.TextField;
import javafx.scene.input.KeyCode;
import javafx.scene.input.KeyEvent;
import javafx.scene.input.MouseEvent;
import javafx.scene.layout.Pane;
import javafx.scene.layout.Region;
import javafx.scene.layout.StackPane;
import javafx.util.StringConverter;
import javafx.stage.Window;
import javafx.beans.value.ChangeListener;

/** Material picker composition using the public JavaFX skin and popup APIs. */
public abstract class JFXGenericPickerSkin<T> extends SkinBase<ComboBoxBase<T>> {
    protected Pane arrowButton;
    protected PopupControl popup;
    private Node display;
    private TextField observedEditor;
    private Window observedOwner;
    private final ChangeListener<Boolean> editorFocus = (obs, oldValue, focused) -> {
        if (!focused) reflectSetTextFromTextFieldIntoComboBoxValue();
    };
    private final EventHandler<MouseEvent> mousePressed = event -> {
        if (getEditor() == null || isArrow(event.getTarget())) {
            getSkinnable().requestFocus();
            if (getSkinnable().isShowing()) getSkinnable().hide(); else getSkinnable().show();
            event.consume();
        }
    };
    private final EventHandler<KeyEvent> keyPressed = event -> {
        KeyCode code = event.getCode();
        if (code == KeyCode.ESCAPE) { getSkinnable().hide(); event.consume(); }
        else if (code == KeyCode.F4 || (event.isAltDown() && code == KeyCode.DOWN)) {
            if (getSkinnable().isShowing()) getSkinnable().hide(); else getSkinnable().show();
            event.consume();
        } else if (!getSkinnable().isEditable() && (code == KeyCode.SPACE || code == KeyCode.ENTER)) {
            if (getSkinnable().isShowing()) getSkinnable().hide(); else getSkinnable().show();
            event.consume();
        } else if (event.isAltDown() && code == KeyCode.UP) {
            getSkinnable().hide(); event.consume();
        } else if (code == KeyCode.ENTER && getSkinnable().isEditable()) {
            reflectSetTextFromTextFieldIntoComboBoxValue();
        }
    };

    public JFXGenericPickerSkin(ComboBoxBase<T> control) {
        super(control);
        Region arrow = new Region();
        arrow.getStyleClass().add("arrow");
        arrowButton = new StackPane(arrow);
        arrowButton.getStyleClass().add("arrow-button");
        getChildren().add(arrowButton);
        control.addEventFilter(MouseEvent.MOUSE_PRESSED, mousePressed);
        control.addEventFilter(KeyEvent.KEY_PRESSED, keyPressed);
        if (control instanceof ColorPicker) registerChangeListener(control.showingProperty(), obs -> { if (control.isShowing()) show(); else hide(); });
        registerChangeListener(control.editableProperty(), obs -> reflectUpdateDisplayArea());
        registerChangeListener(control.sceneProperty(), obs -> { if (control.getScene() == null) control.hide(); });
        registerChangeListener(control.localToSceneTransformProperty(), obs -> reposition());
        registerChangeListener(control.boundsInLocalProperty(), obs -> reposition());
        registerChangeListener(control.disabledProperty(), obs -> { if (control.isDisabled()) control.hide(); });
    }

    private boolean isArrow(Object target) {
        Node node = target instanceof Node ? (Node) target : null;
        while (node != null) {
            if (node == arrowButton) return true;
            node = node.getParent();
        }
        return false;
    }

    protected abstract Node getPopupContent();
    protected abstract TextField getEditor();
    protected abstract StringConverter<T> getConverter();
    public abstract Node getDisplayNode();

    public void show() {
        if (getSkinnable().getScene() == null || getSkinnable().getScene().getWindow() == null) return;
        Window owner = getSkinnable().getScene().getWindow();
        if (!owner.isShowing()) { getSkinnable().hide(); return; }
        if (owner != observedOwner) {
            if (observedOwner != null) {
                unregisterChangeListeners(observedOwner.xProperty());
                unregisterChangeListeners(observedOwner.yProperty());
                unregisterChangeListeners(observedOwner.showingProperty());
            }
            observedOwner = owner;
            registerChangeListener(owner.xProperty(), obs -> reposition());
            registerChangeListener(owner.yProperty(), obs -> reposition());
            registerChangeListener(owner.showingProperty(), obs -> { if (!owner.isShowing()) getSkinnable().hide(); });
        }
        if (popup == null) {
            Node content = getPopupContent();
            popup = new PopupControl() {
                @Override public Styleable getStyleableParent() { return JFXGenericPickerSkin.this.getSkinnable(); }
            };
            popup.setSkin(new Skin<PopupControl>() {
                public PopupControl getSkinnable() { return popup; }
                public Node getNode() { return content; }
                public void dispose() { }
            });
            popup.setAutoHide(true);
            popup.setHideOnEscape(true);
            popup.setOnHidden(event -> getSkinnable().hide());
        }
        if (!popup.isShowing()) {
            Bounds bounds = getSkinnable().localToScreen(getSkinnable().getBoundsInLocal());
            if (bounds != null) popup.show(getSkinnable(), bounds.getMinX(), bounds.getMaxY());
        }
    }

    public void hide() { if (popup != null) popup.hide(); }

    private void reposition() {
        if (popup != null && popup.isShowing()) {
            Bounds bounds = getSkinnable().localToScreen(getSkinnable().getBoundsInLocal());
            if (bounds != null) {
                popup.setAnchorX(bounds.getMinX());
                popup.setAnchorY(bounds.getMaxY());
            }
        }
    }

    protected void reflectUpdateDisplayArea() {
        Node next = getDisplayNode();
        if (next != display && next != null) {
            if (display != null) getChildren().remove(display);
            display = next;
            if (!getChildren().contains(next)) getChildren().add(0, next);
        }
        TextField editor = getEditor();
        if (editor != observedEditor) {
            if (observedEditor != null) observedEditor.focusedProperty().removeListener(editorFocus);
            observedEditor = editor;
            if (editor != null) editor.focusedProperty().addListener(editorFocus);
        }
        if (editor != null) editor.setEditable(getSkinnable().isEditable());
        getSkinnable().requestLayout();
    }

    protected void reflectSetTextFromTextFieldIntoComboBoxValue() {
        TextField editor = getEditor();
        StringConverter<T> converter = getConverter();
        if (editor != null && converter != null && getSkinnable().isEditable()) {
            try { getSkinnable().setValue(converter.fromString(editor.getText())); }
            catch (RuntimeException invalidInput) { reflectUpdateDisplayNode(); }
        }
    }

    protected TextField reflectGetEditableInputNode() { return getEditor(); }

    protected void reflectUpdateDisplayNode() {
        TextField editor = getEditor();
        StringConverter<T> converter = getConverter();
        if (editor != null && converter != null) editor.setText(converter.toString(getSkinnable().getValue()));
        getSkinnable().requestLayout();
    }

    @Override protected void layoutChildren(double x, double y, double width, double height) {
        // Color picker owns its rippler children/layout; date/time own an editor + arrow.
        if (getEditor() == null) {
            for (Node child : getChildren()) if (child instanceof Region) ((Region) child).resizeRelocate(x, y, width, height);
            return;
        }
        reflectUpdateDisplayArea();
        double arrowWidth = Math.max(24, arrowButton.prefWidth(height));
        if (display instanceof Region) ((Region) display).resizeRelocate(x, y, Math.max(0, width - arrowWidth), height);
        arrowButton.resizeRelocate(x + width - arrowWidth, y, arrowWidth, height);
    }

    @Override protected double computePrefWidth(double height, double top, double right, double bottom, double left) {
        Node node = getDisplayNode();
        return left + right + (node instanceof Region ? ((Region) node).prefWidth(height) : 100) + (getEditor() == null ? 0 : 24);
    }
    @Override protected double computePrefHeight(double width, double top, double right, double bottom, double left) {
        Node node = getDisplayNode();
        return top + bottom + (node instanceof Region ? ((Region) node).prefHeight(width) : 26);
    }
    @Override public void dispose() {
        hide();
        getSkinnable().removeEventFilter(MouseEvent.MOUSE_PRESSED, mousePressed);
        getSkinnable().removeEventFilter(KeyEvent.KEY_PRESSED, keyPressed);
        if (observedEditor != null) observedEditor.focusedProperty().removeListener(editorFocus);
        super.dispose();
    }
}
