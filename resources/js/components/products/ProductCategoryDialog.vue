<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { Check, Pencil, Plus, Trash2, X } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, store, update } from '@/routes/product-categories';

const open = defineModel<boolean>('open', { required: true });
defineProps<{ categories: { id: number; name: string }[] }>();
const form = useHttp<{ name: string }>({ name: '' });
const editForm = useHttp<{ name: string }>({ name: '' });
const deleteForm = useHttp({});
const editingId = ref<number | null>(null);
const deletingId = ref<number | null>(null);
const actionError = ref('');

function createCategory(): void {
    form.post(store().url, {
        onSuccess: () => {
            form.reset();
            open.value = false;
            router.reload({ only: ['categories'] });
        },
    });
}

function startEditing(category: { id: number; name: string }): void {
    actionError.value = '';
    editingId.value = category.id;
    editForm.name = category.name;
    editForm.clearErrors();
}

function cancelEditing(): void {
    editingId.value = null;
    editForm.reset();
    editForm.clearErrors();
}

function saveCategory(categoryId: number): void {
    editForm.patch(update(categoryId).url, {
        onSuccess: () => {
            cancelEditing();
            router.reload({ only: ['categories', 'products', 'product'] });
        },
    });
}

function deleteCategory(categoryId: number): void {
    deletingId.value = categoryId;
    actionError.value = '';
    deleteForm.delete(destroy(categoryId).url, {
        onSuccess: () => router.reload({ only: ['categories', 'products'] }),
        onError: (errors) => {
            actionError.value =
                errors.category ?? 'Não foi possível excluir a categoria.';
        },
        onFinish: () => {
            deletingId.value = null;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Nova categoria</DialogTitle>
                <DialogDescription>
                    Crie, renomeie e remova as categorias do seu cardápio.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="createCategory">
                <div class="grid gap-2">
                    <Label for="category-name">Nome da categoria</Label>
                    <Input
                        id="category-name"
                        v-model="form.name"
                        maxlength="80"
                        autocomplete="off"
                        placeholder="Ex.: Sobremesas"
                        autofocus
                    />
                    <p v-if="form.errors.name" class="text-sm text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        @click="open = false"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        class="gap-2"
                        :disabled="form.processing"
                    >
                        <Plus class="size-4" />
                        {{ form.processing ? 'Criando...' : 'Criar categoria' }}
                    </Button>
                </DialogFooter>
            </form>

            <div class="space-y-2 border-t pt-4">
                <p class="text-sm font-medium">Categorias cadastradas</p>
                <p
                    v-if="actionError"
                    role="alert"
                    class="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                >
                    {{ actionError }}
                </p>
                <div
                    v-for="category in categories"
                    :key="category.id"
                    class="flex items-center gap-2 rounded-xl border p-2"
                >
                    <form
                        v-if="editingId === category.id"
                        class="flex min-w-0 flex-1 items-center gap-2"
                        @submit.prevent="saveCategory(category.id)"
                    >
                        <div class="min-w-0 flex-1">
                            <Input
                                v-model="editForm.name"
                                maxlength="80"
                                aria-label="Nome da categoria"
                                autofocus
                            />
                            <p
                                v-if="editForm.errors.name"
                                class="mt-1 text-xs text-destructive"
                            >
                                {{ editForm.errors.name }}
                            </p>
                        </div>
                        <Button
                            type="submit"
                            size="icon-sm"
                            :disabled="editForm.processing"
                            aria-label="Salvar nome"
                        >
                            <Check class="size-4" />
                        </Button>
                        <Button
                            type="button"
                            size="icon-sm"
                            variant="ghost"
                            aria-label="Cancelar edição"
                            @click="cancelEditing"
                        >
                            <X class="size-4" />
                        </Button>
                    </form>
                    <template v-else>
                        <span class="min-w-0 flex-1 truncate px-2 text-sm">
                            {{ category.name }}
                        </span>
                        <Button
                            type="button"
                            size="icon-sm"
                            variant="ghost"
                            :aria-label="`Editar ${category.name}`"
                            @click="startEditing(category)"
                        >
                            <Pencil class="size-4" />
                        </Button>
                        <Button
                            type="button"
                            size="icon-sm"
                            variant="ghost"
                            class="text-destructive hover:text-destructive"
                            :disabled="deletingId === category.id"
                            :aria-label="`Excluir ${category.name}`"
                            @click="deleteCategory(category.id)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </template>
                </div>
                <p
                    v-if="!categories.length"
                    class="rounded-xl border border-dashed p-4 text-center text-sm text-muted-foreground"
                >
                    Nenhuma categoria cadastrada ainda.
                </p>
                <p class="text-xs text-muted-foreground">
                    Categorias com produtos só podem ser excluídas depois que os
                    itens forem movidos para outra categoria.
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>
