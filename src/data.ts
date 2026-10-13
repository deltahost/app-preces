export type Prayer = { id: string; title: string; text: string; favorite?: boolean; audioUrl?: string };

export const verses = [
  ['Domingo', 'Este é o dia que o Senhor fez; alegremo-nos e exultemos nele.', 'Salmos 118:24'],
  ['Segunda', 'Tudo posso naquele que me fortalece.', 'Filipenses 4:13'],
  ['Terça', 'Entrega o teu caminho ao Senhor; confia nele, e ele agirá.', 'Salmos 37:5'],
  ['Quarta', 'O Senhor é o meu pastor; nada me faltará.', 'Salmos 23:1'],
  ['Quinta', 'Aquietai-vos e sabei que eu sou Deus.', 'Salmos 46:10'],
  ['Sexta', 'O amor é paciente, o amor é bondoso.', '1 Coríntios 13:4'],
  ['Sábado', 'Os que esperam no Senhor renovam as suas forças.', 'Isaías 40:31'],
];

export const categories = [
  ['Pessoal', 'person-outline', '#7B5C45'], ['Família', 'people-outline', '#9B6C72'],
  ['Trabalho', 'briefcase-outline', '#55737B'], ['Relacionamento', 'heart-outline', '#A15D5D'],
  ['Saúde', 'medkit-outline', '#5C8872'], ['Estudos', 'book-outline', '#6C6B93'],
  ['Finanças', 'wallet-outline', '#98743F'], ['Conquistas', 'trophy-outline', '#A87B36'],
  ['Proteção', 'shield-checkmark-outline', '#536E8A'],
] as const;

export const OUR_FATHER = 'Pai nosso que estais nos céus, santificado seja o vosso nome. Venha a nós o vosso reino. Seja feita a vossa vontade, assim na terra como no céu. O pão nosso de cada dia nos dai hoje. Perdoai as nossas ofensas, assim como nós perdoamos a quem nos tem ofendido. E não nos deixeis cair em tentação, mas livrai-nos do mal.';

export const initialPrayers: Prayer[] = [
  { id: 'p1', title: 'Oração da manhã', text: 'Senhor, obrigado por este novo dia. Guia meus passos, guarda minha família e faz de mim instrumento da tua paz.', favorite: true },
  { id: 'p2', title: 'Pela minha família', text: 'Deus de amor, envolve minha família com tua proteção. Dá-nos união, saúde, sabedoria e fé.' },
];

export const media = [
  { title: 'Salmo 91 para dormir em paz', type: 'Áudio', time: '08:42', color: '#506B61' },
  { title: 'Oração poderosa da manhã', type: 'Vídeo', time: '05:18', color: '#8A624C' },
  { title: 'Meditação: confie em Deus', type: 'Áudio', time: '11:05', color: '#665D7B' },
  { title: 'Prece para fortalecer a fé', type: 'Vídeo', time: '06:33', color: '#796745' },
];
