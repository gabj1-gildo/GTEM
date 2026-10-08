<?php
return [
    'required' => 'O campo :attribute é obrigatório.', 'string' => 'Informe um texto em :attribute.',
    'email' => 'Informe um e-mail válido.', 'integer' => 'Informe um número inteiro em :attribute.',
    'boolean' => 'Valor inválido para :attribute.', 'date' => 'Informe uma data válida.',
    'unique' => 'Este valor já está cadastrado.', 'exists' => 'O cadastro selecionado não existe.',
    'in' => 'Selecione uma opção válida.', 'confirmed' => 'A confirmação da senha não confere.',
    'after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
    'alpha_dash' => 'Use apenas letras, números, hífen e sublinhado.',
    'min' => ['string' => 'O campo :attribute deve ter pelo menos :min caracteres.', 'numeric' => 'O valor mínimo é :min.'],
    'max' => ['string' => 'O campo :attribute deve ter no máximo :max caracteres.', 'numeric' => 'O valor máximo é :max.'],
    'password' => ['mixed' => 'A senha deve conter letras maiúsculas e minúsculas.', 'numbers' => 'A senha deve conter números.'],
    'attributes' => ['name' => 'nome','email' => 'e-mail','password' => 'senha','roleId' => 'perfil','reason' => 'justificativa'],
];
