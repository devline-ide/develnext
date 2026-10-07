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

package com.jfoenix.adapters;

import java.lang.reflect.AccessibleObject;
import java.lang.reflect.Field;
import java.lang.reflect.InvocationTargetException;
import java.lang.reflect.Method;

/**
 * Reflection on accessible application classes; never bypasses Java modules.
 *
 * @author huang
 */
public class ReflectionHelper {

    private static void setAccessible(AccessibleObject obj) {
        if (!obj.trySetAccessible()) throw new IllegalStateException("Member is not accessible through the public module API: " + obj);
    }

    public static <T> T invoke(Class cls, Object obj, String methodName) {
        try {
            Method method = cls.getDeclaredMethod(methodName);
            setAccessible(method);
            return (T) method.invoke(obj);
        } catch (Throwable ex) {
            throw new InternalError(ex);
        }
    }

    public static <T> T invoke(Object obj, String methodName) {
        return invoke(obj.getClass(), obj, methodName);
    }

    public static Method getMethod(Class cls, String methodName) {
        try {
            Method method = cls.getDeclaredMethod(methodName);
            setAccessible(method);
            return method;
        } catch (Throwable ex) {
            throw new InternalError(ex);
        }
    }

    public static Field getField(Class cls, String fieldName) {
        try {
            Field field = cls.getDeclaredField(fieldName);
            setAccessible(field);
            return field;
        } catch (Throwable ex) {
            return null;
        }
    }

    public static <T> T getFieldContent(Object obj, String fieldName) {
        return getFieldContent(obj.getClass(), obj, fieldName);
    }

    public static <T> T getFieldContent(Class cls, Object obj, String fieldName) {
        try {
            Field field = cls.getDeclaredField(fieldName);
            setAccessible(field);
            return (T) field.get(obj);
        } catch (Throwable ex) {
            return null;
        }
    }

    public static void setFieldContent(Class cls, Object obj, String fieldName, Object content) {
        try {
            Field field = cls.getDeclaredField(fieldName);
            setAccessible(field);
            field.set(obj, content);
        } catch (Throwable ex) {
        }
    }
}
