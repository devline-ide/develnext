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

import com.jfoenix.controls.JFXTextArea;
import javafx.collections.ListChangeListener;
import javafx.geometry.Insets;
import javafx.scene.Node;
import javafx.scene.Parent;
import javafx.scene.control.ScrollPane;
import javafx.scene.control.skin.TextAreaSkin;
import javafx.scene.layout.Background;
import javafx.scene.layout.BackgroundFill;
import javafx.scene.layout.CornerRadii;
import javafx.scene.layout.Region;
import javafx.scene.paint.Color;
import javafx.scene.text.Text;

/**
 * <h1>Material Design TextArea Skin</h1>
 *
 * @author Shadi Shaheen
 * @version 2.0
 * @since 2017-01-25
 */
public class JFXTextAreaSkin extends TextAreaSkin {

    private boolean invalid = true;
    private boolean disposed;

    private ScrollPane scrollPane;
    private Text promptText;
    private Parent nativeContent;
    private Text nativePrompt;
    private double nativePromptOpacity;
    private final ListChangeListener<Node> nativePromptListener = change -> hideNativePrompt();

    private ValidationPane<JFXTextArea> errorContainer;
    private PromptLinesWrapper<JFXTextArea> linesWrapper;

    public JFXTextAreaSkin(JFXTextArea textArea) {
        super(textArea);
        // init text area properties
        scrollPane = (ScrollPane) getChildren().get(0);
        nativeContent = (Parent) scrollPane.getContent();
        nativeContent.getChildrenUnmodifiable().addListener(nativePromptListener);
        hideNativePrompt();
        textArea.setWrapText(true);

        linesWrapper = new PromptLinesWrapper<>(
            textArea,
            promptTextFillProperty(),
            textArea.textProperty(),
            textArea.promptTextProperty(),
            () -> promptText);

        linesWrapper.init(() -> createPromptNode(), scrollPane);
        errorContainer = new ValidationPane<>(textArea);
        getChildren().addAll(linesWrapper.line, linesWrapper.focusedLine, linesWrapper.promptContainer, errorContainer);

        registerChangeListener(textArea.disableProperty(), obs -> linesWrapper.updateDisabled());
        registerChangeListener(textArea.focusColorProperty(), obs -> linesWrapper.updateFocusColor());
        registerChangeListener(textArea.unFocusColorProperty(), obs -> linesWrapper.updateUnfocusColor());
        registerChangeListener(textArea.disableAnimationProperty(), obs -> errorContainer.updateClip());

    }


    @Override
    protected void layoutChildren(final double x, final double y, final double w, final double h) {
        super.layoutChildren(x, y, w, h);

        final double height = getSkinnable().getHeight();
        linesWrapper.layoutLines(x, y, w, h, height, promptText == null ? 0 : promptText.getLayoutBounds().getHeight() + 3);
        errorContainer.layoutPane(x, height + linesWrapper.focusedLine.getHeight(), w, h);
        linesWrapper.updateLabelFloatLayout();

        if (invalid) {
            invalid = false;
            // set the default background of text area viewport to white
            Node viewport = scrollPane.lookup(".viewport");
            if (viewport instanceof Region) {
                Region viewPort = (Region) viewport;
                viewPort.setBackground(new Background(new BackgroundFill(Color.TRANSPARENT,
                    CornerRadii.EMPTY,
                    Insets.EMPTY)));
                // reapply css of scroll pane in case set by the user
                viewPort.applyCss();
            }
            errorContainer.invalid(w);
            // focus
            linesWrapper.invalid();
        }
    }

    private void createPromptNode() {
        if (promptText != null || !linesWrapper.usePromptText.get()) {
            return;
        }
        promptText = new Text();
        promptText.setManaged(false);
        promptText.getStyleClass().add("text");
        promptText.visibleProperty().bind(linesWrapper.usePromptText);
        promptText.fontProperty().bind(getSkinnable().fontProperty());
        promptText.textProperty().bind(getSkinnable().promptTextProperty());
        promptText.fillProperty().bind(linesWrapper.animatedPromptTextFill);
        promptText.setLayoutX(1);
        promptText.setTranslateX(1);
        promptText.getTransforms().add(linesWrapper.promptTextScale);
        linesWrapper.promptContainer.getChildren().add(promptText);
        if (getSkinnable().isFocused() && ((JFXTextArea) getSkinnable()).isLabelFloat()) {
            promptText.setTranslateY(-Math.floor(scrollPane.getHeight()));
            linesWrapper.promptTextScale.setX(0.85);
            linesWrapper.promptTextScale.setY(0.85);
        }

    }

    private void hideNativePrompt() {
        // TextArea's editable text is inside a Group; its default prompt is the direct Text child.
        // Keep native editing/caret layout intact and render the floating prompt in our own container.
        for (Node node : nativeContent.getChildrenUnmodifiable()) {
            if (node instanceof Text && node.getStyleClass().contains("text")) {
                Text text = (Text) node;
                if (nativePrompt != text) {
                    nativePrompt = text;
                    nativePromptOpacity = text.getOpacity();
                }
                nativePrompt.setOpacity(0);
            }
        }
    }

    @Override
    public void dispose() {
        if (disposed) {
            return;
        }
        disposed = true;
        nativeContent.getChildrenUnmodifiable().removeListener(nativePromptListener);
        if (nativePrompt != null) {
            nativePrompt.setOpacity(nativePromptOpacity);
        }
        if (promptText != null) {
            promptText.visibleProperty().unbind();
            promptText.fontProperty().unbind();
            promptText.textProperty().unbind();
            promptText.fillProperty().unbind();
        }
        super.dispose();
    }
}
