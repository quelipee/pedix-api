export type MenuProduct = {
    id: number;
    name: string;
    category: string;
    description: string | null;
    ingredients: string[];
    price: string;
    optionGroups: {
        id: number;
        name: string;
        minSelections: number;
        maxSelections: number;
        options: { id: number; name: string; price: string; active: boolean }[];
    }[];
};
