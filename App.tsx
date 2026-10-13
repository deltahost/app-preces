import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaView } from 'react-native-safe-area-context';
import Home from './src/Home';
import { Favorites, Father, MyPrayers, Videos } from './src/Screens';
import { Drawer, Premium, Reader } from './src/Overlays';
import { initialPrayers, Prayer } from './src/data';
import { dark, light } from './src/theme';
import { Icon } from './src/ui';
import { speakNarration } from './src/services/tts';
import { useFocusEffect } from 'expo-router';
import { ContentCategory, ContentMediaCategory, loadPublishedContent, loadRecentPrayerIds, PublishedMedia, PublishedPrayer, recordPrayerAccess } from './src/services/content';
import { DailyNarrationLimitError, playPrayerNarration } from './src/services/prayerNarration';

type Tab='home'|'videos'|'prayers'|'favorites'|'father';
type Stored={dark:boolean;reminder:boolean;premium:boolean;prayers:Prayer[]};
const KEY='@preces/state';

export default function App(){
 const [tab,setTab]=useState<Tab>('home'); const [darkMode,setDarkMode]=useState(false); const [drawer,setDrawer]=useState(false); const [premiumOpen,setPremiumOpen]=useState(false); const [premium,setPremium]=useState(false); const [reminder,setReminder]=useState(true); const [prayers,setPrayersRaw]=useState<Prayer[]>(initialPrayers); const [reader,setReader]=useState<Prayer|null>(null); const [contentCategories,setContentCategories]=useState<ContentCategory[]>([]); const [publishedPrayers,setPublishedPrayers]=useState<PublishedPrayer[]>([]); const [mediaCategories,setMediaCategories]=useState<ContentMediaCategory[]>([]); const [publishedMedia,setPublishedMedia]=useState<PublishedMedia[]>([]); const [recentPrayerIds,setRecentPrayerIds]=useState<string[]>([]); const p=darkMode?dark:light;
 useEffect(()=>{AsyncStorage.getItem(KEY).then(raw=>{if(!raw)return;const x:Stored=JSON.parse(raw);setDarkMode(x.dark||false);setReminder(x.reminder??true);setPremium(x.premium||false);setPrayersRaw(x.prayers||initialPrayers)}).catch(()=>{})},[]);
 useEffect(()=>{loadPublishedContent().then(content=>{setContentCategories(content.categories);setPublishedPrayers(content.prayers);setMediaCategories(content.mediaCategories);setPublishedMedia(content.media)}).catch(()=>{})},[]);
 useFocusEffect(useCallback(()=>{loadRecentPrayerIds().then(setRecentPrayerIds).catch(()=>{})},[]));
 const persist=(next:Partial<Stored>)=>AsyncStorage.setItem(KEY,JSON.stringify({dark:darkMode,reminder,premium,prayers,...next}));
 const setPrayers=(x:Prayer[])=>{setPrayersRaw(x);persist({prayers:x})};
 const setDark=(x:boolean)=>{setDarkMode(x);persist({dark:x})}; const setReminderSafe=(x:boolean)=>{setReminder(x);persist({reminder:x})};
 const speak=(text:string,voice:'male'|'female'='female')=>{void speakNarration(text,voice)};
 const listenPrayer=async(prayer:Prayer,onPhase?:Parameters<typeof playPrayerNarration>[2])=>{
  try{await playPrayerNarration(prayer,premium,onPhase)}catch(error){onPhase?.('idle');if(error instanceof DailyNarrationLimitError){Alert.alert('Limite diário utilizado','Na versão gratuita você pode ouvir 1 oração por dia. Assine o Premium para ouvir sem limites.',[{text:'Agora não'},{text:'Ver Premium',onPress:()=>setPremiumOpen(true)}]);return}Alert.alert('Não foi possível reproduzir','Confira sua conexão e o link do áudio, depois tente novamente.')}
 };
 const openPrayer=async(prayer:Prayer)=>{setReader(prayer);if(prayer.id.startsWith('published-'))setRecentPrayerIds(await recordPrayerAccess(prayer.id))};
 const subscribe=(plan:'monthly'|'annual')=>{setPremium(true);persist({premium:true});setPremiumOpen(false);Alert.alert('Premium ativado',`Plano ${plan==='annual'?'anual':'mensal'} ativado neste protótipo.`)};
 const reset=()=>Alert.alert('Reiniciar o app?','Suas preferências, histórico e orações pessoais serão apagados.',[{text:'Cancelar'},{text:'Reiniciar',style:'destructive',onPress:async()=>{await AsyncStorage.multiRemove([KEY,'@preces/free-narration-date','@preces/free-narration-date-v2','@preces/recent-prayers-v1']);setPrayersRaw(initialPrayers);setRecentPrayerIds([]);setPremium(false);setDarkMode(false);setReminder(true);setDrawer(false)}}]);
 const toggle=()=>{if(!reader)return;const updated={...reader,favorite:!reader.favorite};setReader(updated);if(prayers.some(x=>x.id===reader.id))setPrayers(prayers.map(x=>x.id===reader.id?updated:x))};
 const tabs=useMemo(()=>[
  {key:'home',icon:'chatbubble-ellipses-outline',active:'chatbubble-ellipses',label:'Comentar',main:false},
  {key:'videos',icon:'play-circle-outline',active:'play-circle',label:'Vídeos',main:false},
  {key:'prayers',icon:'add-circle',active:'add-circle',label:'Minhas orações',main:true},
  {key:'favorites',icon:'heart-outline',active:'heart',label:'Favoritos',main:false},
  {key:'father',icon:'book-outline',active:'book',label:'Pai Nosso',main:false},
 ] as const,[]);
 return <SafeAreaView edges={['top','bottom','left','right']} style={[s.app,{backgroundColor:p.bg}]}><StatusBar style={darkMode?'light':'dark'}/><View style={s.header}><Pressable hitSlop={12} style={s.side} onPress={()=>setDrawer(true)}><Icon name="menu" color={p.text} size={27}/></Pressable><View style={s.logo}><Icon name="sparkles" size={18} color={p.gold}/><Text style={[s.logoText,{color:p.text}]}>Preces</Text></View><Pressable hitSlop={8} onPress={()=>setPremiumOpen(true)} style={[s.premiumTop,{borderColor:p.gold}]}><Icon name={premium?'diamond':'diamond-outline'} size={14} color={p.gold}/><Text style={[s.premiumText,{color:p.gold}]}>{premium?'PREMIUM':'ASSINAR'}</Text></Pressable></View>
 <View style={{flex:1}}>{tab==='home'&&<Home p={p} premium={premium} prayers={prayers} contentCategories={contentCategories} publishedPrayers={publishedPrayers} recentPrayerIds={recentPrayerIds} goPremium={()=>setPremiumOpen(true)} speak={speak} openPrayer={openPrayer}/>} {tab==='videos'&&<Videos p={p} premium={premium} media={publishedMedia} categories={mediaCategories} goPremium={()=>setPremiumOpen(true)}/>} {tab==='prayers'&&<MyPrayers p={p} prayers={prayers} setPrayers={setPrayers} premium={premium} listen={listenPrayer}/>} {tab==='favorites'&&<Favorites p={p} prayers={prayers} open={openPrayer}/>} {tab==='father'&&<Father p={p} speak={speak}/>}</View>
 {!premium&&<BottomAd p={p}/>}<View style={[s.bottom,{backgroundColor:p.surface,borderColor:p.border}]}>{tabs.map(x=><TouchableOpacity key={x.key} activeOpacity={0.65} style={s.tab} onPress={()=>setTab(x.key)}>{x.main?<View style={[s.mainTab,{backgroundColor:p.primary,borderColor:p.surface}]}><Icon name={x.icon} size={31} color="#fff"/></View>:<Icon name={tab===x.key?x.active:x.icon} size={23} color={tab===x.key?p.primary:p.muted}/>}<Text numberOfLines={1} style={[s.tabLabel,{color:tab===x.key?p.primary:p.muted},x.main&&{marginTop:3}]}>{x.label}</Text></TouchableOpacity>)}</View>
 <Drawer visible={drawer} close={()=>setDrawer(false)} p={p} dark={darkMode} setDark={setDark} reminder={reminder} setReminder={setReminderSafe} goPremium={()=>setPremiumOpen(true)} reset={reset}/><Premium visible={premiumOpen} close={()=>setPremiumOpen(false)} p={p} onSubscribe={subscribe}/><Reader prayer={reader} close={()=>setReader(null)} p={p} premium={premium} listen={listenPrayer} toggle={toggle}/></SafeAreaView>
}

function BottomAd({p}:{p:typeof light}){
 return <Pressable accessibilityRole="button" accessibilityLabel="Anúncio patrocinado" onPress={()=>Alert.alert('Anúncio','Aqui será aberto o anúncio configurado no Google AdMob.')} style={[s.fixedAd,{backgroundColor:p.surface,borderColor:p.border}]}><View style={[s.adIcon,{backgroundColor:p.primary}]}><Icon name="book" size={25} color="#fff"/></View><View style={s.adCopy}><View style={s.adLabel}><Text style={s.adLabelText}>ANÚNCIO</Text></View><Text numberOfLines={1} style={[s.adTitle,{color:p.text}]}>Bíblia Sagrada Áudio + Offline</Text><Text style={[s.adMeta,{color:p.muted}]}>Google Play</Text></View><View style={[s.install,{backgroundColor:p.primary}]}><Text style={s.installText}>INSTALAR</Text></View></Pressable>
}

const s=StyleSheet.create({app:{flex:1},header:{height:58,flexDirection:'row',alignItems:'center',justifyContent:'space-between',paddingHorizontal:18},side:{width:56,height:44,justifyContent:'center'},logo:{flexDirection:'row',alignItems:'center',gap:7},logoText:{fontSize:23,fontWeight:'800',letterSpacing:.4},premiumTop:{minWidth:86,height:36,paddingHorizontal:10,borderRadius:18,borderWidth:1,flexDirection:'row',alignItems:'center',justifyContent:'center',gap:5},premiumText:{fontSize:11,fontWeight:'900',letterSpacing:.7},fixedAd:{height:70,borderTopWidth:1,borderBottomWidth:1,flexDirection:'row',alignItems:'center',paddingHorizontal:12,gap:10},adIcon:{width:44,height:44,borderRadius:11,alignItems:'center',justifyContent:'center'},adCopy:{flex:1},adLabel:{alignSelf:'flex-start',backgroundColor:'#E7A827',borderRadius:3,paddingHorizontal:5,paddingVertical:1,marginBottom:2},adLabelText:{color:'#fff',fontSize:7,fontWeight:'900'},adTitle:{fontSize:13,fontWeight:'800'},adMeta:{fontSize:10,marginTop:2},install:{height:38,minWidth:84,borderRadius:19,alignItems:'center',justifyContent:'center',paddingHorizontal:13},installText:{color:'#fff',fontSize:11,fontWeight:'900'},bottom:{height:76,borderTopWidth:1,flexDirection:'row',paddingHorizontal:4,paddingBottom:3},tab:{flex:1,alignItems:'center',justifyContent:'center',minHeight:58},tabLabel:{fontSize:9,fontWeight:'700',marginTop:5,maxWidth:66},mainTab:{width:49,height:49,borderRadius:25,alignItems:'center',justifyContent:'center',marginTop:-22,borderWidth:4}});
