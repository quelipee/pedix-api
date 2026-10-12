<?php

return [
    'accepted' => 'O campo :attribute precisa ser aceito.',
    'confirmed' => 'A confirmação de :attribute não corresponde.',
    'current_password' => 'A senha informada está incorreta.',
    'email' => 'Informe um endereço de e-mail válido.',
    'exists' => 'O valor selecionado para :attribute é inválido.',
    'max' => [
        'array' => 'O campo :attribute não pode ter mais que :max itens.',
        'file' => 'O arquivo :attribute não pode ser maior que :max kilobytes.',
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute não pode ter mais que :max caracteres.',
    ],
    'min' => [
        'array' => 'O campo :attribute precisa ter pelo menos :min itens.',
        'file' => 'O arquivo :attribute precisa ter pelo menos :min kilobytes.',
        'numeric' => 'O campo :attribute precisa ser pelo menos :min.',
        'string' => 'O campo :attribute precisa ter pelo menos :min caracteres.',
    ],
    'password' => [
        'letters' => 'A senha precisa conter pelo menos uma letra.',
        'mixed' => 'A senha precisa conter letras maiúsculas e minúsculas.',
        'numbers' => 'A senha precisa conter pelo menos um número.',
        'symbols' => 'A senha precisa conter pelo menos um símbolo.',
        'uncompromised' => 'Esta senha apareceu em um vazamento de dados. Escolha outra senha.',
    ],
    'required' => 'Preencha o campo :attribute.',
    'string' => 'O campo :attribute precisa ser um texto.',
    'unique' => 'Este :attribute já está em uso.',
    'attributes' => [
        'name' => 'nome',
        'email' => 'e-mail',
        'password' => 'senha',
        'password_confirmation' => 'confirmação de senha',
        'code' => 'código',
        'recovery_code' => 'código de recuperação',
    ],
];
