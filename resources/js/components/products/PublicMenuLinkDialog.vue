<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Copy, ExternalLink, Link } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show } from '@/routes/public-menu';
import { update } from '@/routes/online-menu';

const props = defineProps<{ slug: string; deliveryFee: string | null }>();
const deliveryEnabled = ref(props.deliveryFee !== null);
const form = useForm({ online_delivery_fee: props.deliveryFee ?? '' });
const copied = ref(false);
const copyError = ref('');
const menuUrl = new URL(show(props.slug).url, window.location.origin).href;

async function copy(): Promise<void> {
    try {
        await navigator.clipboard.writeText(menuUrl);
        copied.value = true;
        copyError.value = '';
    } catch {
        copyError.value = 'Selecione o link acima e copie manualmente.';
    }
}
</script>

<template>
    <Dialog>
        <DialogTrigger as-child
            ><Button variant="outline" class="gap-2"
                ><Link class="size-4" />Link do cardápio</Button
            ></DialogTrigger
        >
        <DialogContent>
            <DialogHeader
                ><DialogTitle>Seu cardápio online</DialogTitle
                ><DialogDescription
                    >Compartilhe este link com seus clientes. Para receber
                    pedidos, abra a loja na barra superior e mantenha a
                    assinatura ativa.</DialogDescription
                ></DialogHeader
            >
            <div class="space-y-3">
                <Label for="public-menu-url">Link da sua loja</Label>
                <Input
                    id="public-menu-url"
                    :model-value="menuUrl"
                    readonly
                    @focus="($event.target as HTMLInputElement).select()"
                />
                <div class="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="gap-2"
                        @click="copy"
                        ><Copy class="size-4" />{{
                            copied ? 'Copiado!' : 'Copiar link'
                        }}</Button
                    ><Button as-child variant="outline"
                        ><a
                            :href="menuUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            ><ExternalLink class="mr-2 size-4" />Abrir
                            cardápio</a
                        ></Button
                    >
                </div>
                <p v-if="copyError" class="text-sm text-destructive">
                    {{ copyError }}
                </p>
            </div>
            <form
                class="space-y-3 border-t pt-4"
                @submit.prevent="
                    form
                        .transform((data) => ({
                            online_delivery_fee: deliveryEnabled
                                ? data.online_delivery_fee
                                : null,
                        }))
                        .patch(update(slug).url, { preserveScroll: true })
                "
            >
                <label class="flex items-center gap-2 text-sm font-medium"
                    ><input v-model="deliveryEnabled" type="checkbox" />Aceitar
                    entregas no cardápio</label
                >
                <p
                    v-if="!deliveryEnabled"
                    class="text-sm text-muted-foreground"
                >
                    Entrega desativada. Os clientes podem escolher somente
                    retirada.
                </p>
                <Label v-if="deliveryEnabled" for="online-delivery-fee"
                    >Taxa fixa de entrega (R$)</Label
                >
                <Input
                    v-if="deliveryEnabled"
                    id="online-delivery-fee"
                    required
                    v-model="form.online_delivery_fee"
                    type="number"
                    min="0"
                    max="99999999.99"
                    step="0.01"
                    placeholder="Não configurada"
                />
                <p class="text-xs text-muted-foreground">
                    Ative a entrega e informe a taxa para disponibilizar essa
                    opção no link. Use 0 para entrega grátis. Nesta versão, a
                    taxa vale para todos os endereços; confira sua área de
                    atendimento antes de despachar.
                </p>
                <p
                    v-if="form.errors.online_delivery_fee"
                    class="text-sm text-destructive"
                >
                    {{ form.errors.online_delivery_fee }}
                </p>
                <Button type="submit" :disabled="form.processing"
                    >Salvar configuração</Button
                >
            </form>
        </DialogContent>
    </Dialog>
</template>
