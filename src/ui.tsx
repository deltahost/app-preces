import React from 'react';
import { Platform, Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Palette } from './theme';
import { Text } from './typography';

export const shadow = Platform.select({ ios: { shadowColor:'#21160D',shadowOpacity:.08,shadowRadius:16,shadowOffset:{width:0,height:6}}, android:{elevation:3}, default:{} });

export function Icon({ name, size=22, color }: { name:any; size?:number; color:string }) {
  return <Ionicons name={name} size={size} color={color}/>;
}

export function SectionTitle({ title, action, p }: { title:string; action?:string; p:Palette }) {
  return <View style={s.head}><Text style={[s.title,{color:p.text}]}>{title}</Text>{action && <Pressable><Text style={[s.action,{color:p.primary}]}>{action}</Text></Pressable>}</View>;
}

export function Ad({ p }: { p:Palette }) {
  return <View style={[s.ad,{borderColor:p.border,backgroundColor:p.surface}]}><Text style={[s.adTag,{color:p.muted}]}>ANÚNCIO</Text><Text style={[s.adText,{color:p.muted}]}>Espaço reservado</Text></View>;
}

const s=StyleSheet.create({
  head:{marginTop:5,flexDirection:'row',justifyContent:'space-between',alignItems:'center'},
  title:{fontSize:18,fontWeight:'800'},action:{fontSize:12,fontWeight:'800'},
  ad:{height:58,borderWidth:1,borderRadius:12,borderStyle:'dashed',alignItems:'center',justifyContent:'center'},
  adTag:{fontSize:8,letterSpacing:1.4,fontWeight:'800'},adText:{fontSize:11,marginTop:1},
});
