// Six segmented code boxes backed by a single hidden TextInput: handles paste, SMS autofill
// (iOS oneTimeCode / Android sms-otp) and backspace naturally.
import { useEffect, useRef, useState } from "react";
import { Platform, Pressable, StyleSheet, TextInput, View, type TextInputInstance } from "react-native";
import Animated, { useAnimatedStyle, useSharedValue, withRepeat, withSequence, withTiming } from "react-native-reanimated";
import { radius, useTheme } from "@/theme";
import { T } from "./ui";

export function OtpInput({ value, onChange, error, autoFocus = true }: { value: string; onChange: (v: string) => void; error?: boolean; autoFocus?: boolean }) {
  const { c } = useTheme();
  const ref = useRef<TextInputInstance>(null);
  const [focused, setFocused] = useState(false);
  const shake = useSharedValue(0);
  const caret = useSharedValue(1);

  useEffect(() => {
    if (error) shake.value = withSequence(withTiming(-8, { duration: 50 }), withTiming(8, { duration: 50 }), withTiming(-5, { duration: 50 }), withTiming(0, { duration: 50 }));
  }, [error, shake]);
  useEffect(() => {
    caret.value = withRepeat(withTiming(0, { duration: 500 }), -1, true);
  }, [caret]);

  const shakeStyle = useAnimatedStyle(() => ({ transform: [{ translateX: shake.value }] }));
  const caretStyle = useAnimatedStyle(() => ({ opacity: caret.value }));

  return (
    <Pressable onPress={() => ref.current?.focus()} accessibilityLabel={`Verification code, ${value.length} of 6 digits entered`}>
      <Animated.View style={[styles.row, shakeStyle]}>
        {Array.from({ length: 6 }, (_, i) => {
          const ch = value[i];
          const active = focused && i === Math.min(value.length, 5);
          return (
            <View
              key={i}
              style={[
                styles.box,
                {
                  backgroundColor: c.surface,
                  borderColor: error ? c.bad : active ? c.brand : ch ? c.ink3 : c.line,
                  borderWidth: active ? 2 : 1.5,
                },
              ]}
            >
              {ch ? (
                <T v="h1" style={{ fontSize: 24 }}>
                  {ch}
                </T>
              ) : active ? (
                <Animated.View style={[{ width: 2, height: 24, backgroundColor: c.brand }, caretStyle]} />
              ) : null}
            </View>
          );
        })}
      </Animated.View>
      <TextInput
        ref={ref}
        value={value}
        onChangeText={(t) => onChange(t.replace(/\D/g, "").slice(0, 6))}
        keyboardType="number-pad"
        textContentType="oneTimeCode"
        autoComplete={Platform.OS === "android" ? "sms-otp" : "one-time-code"}
        maxLength={6}
        autoFocus={autoFocus}
        onFocus={() => setFocused(true)}
        onBlur={() => setFocused(false)}
        caretHidden
        style={styles.hidden}
        accessibilityLabel="Verification code"
      />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: "row", gap: 8, justifyContent: "center" },
  box: { width: 48, height: 58, borderRadius: radius.sm + 2, alignItems: "center", justifyContent: "center" },
  hidden: { position: "absolute", width: 1, height: 1, opacity: 0.01 },
});
