import React from 'react';
import {
  StyleSheet,
  Text as NativeText,
  TextInput as NativeTextInput,
  type TextInputProps,
  type TextProps,
  type TextStyle,
} from 'react-native';

const normalFonts = {
  400: 'Poppins_400Regular',
  600: 'Poppins_600SemiBold',
  700: 'Poppins_700Bold',
  800: 'Poppins_800ExtraBold',
  900: 'Poppins_900Black',
} as const;

const italicFonts = {
  400: 'Poppins_400Regular_Italic',
  600: 'Poppins_600SemiBold_Italic',
  700: 'Poppins_700Bold_Italic',
  800: 'Poppins_800ExtraBold_Italic',
  900: 'Poppins_900Black_Italic',
} as const;

type PoppinsWeight = keyof typeof normalFonts;

function nearestWeight(value: TextStyle['fontWeight']): PoppinsWeight {
  const weight = typeof value === 'number' ? value : Number(value ?? 400);
  if (weight >= 850) return 900;
  if (weight >= 750) return 800;
  if (weight >= 650) return 700;
  if (weight >= 500) return 600;
  return 400;
}

function poppinsStyle(style: TextProps['style'] | TextInputProps['style']): TextStyle {
  const flattened = StyleSheet.flatten(style) as TextStyle | undefined;
  const weight = nearestWeight(flattened?.fontWeight);
  const family = flattened?.fontStyle === 'italic' ? italicFonts[weight] : normalFonts[weight];
  return { fontFamily: family, fontWeight: 'normal', fontStyle: 'normal' };
}

export function Text({ style, ...props }: TextProps) {
  return <NativeText {...props} style={[style, poppinsStyle(style)]} />;
}

export function TextInput({ style, ...props }: TextInputProps) {
  return <NativeTextInput {...props} style={[style, poppinsStyle(style)]} />;
}
