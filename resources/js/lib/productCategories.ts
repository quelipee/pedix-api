import { Coffee, Sandwich, UtensilsCrossed } from '@lucide/vue';

export function categoryIcon(name: string) {
    const normalized = name.toLocaleLowerCase('pt-BR');

    if (/bebida|suco|café/.test(normalized)) return Coffee;
    if (/lanche|hambúrguer|burger/.test(normalized)) return Sandwich;

    return UtensilsCrossed;
}
