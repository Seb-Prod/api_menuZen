<?php
class Validator
{
    public static function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $rule) {
            // Récupérer la valeur du champ à valider depuis le tableau de données.
            // Si le champ n'existe pas, $value sera null.
            $value = $data[$field] ?? null;

            // Déstructuration de la règle pour extraire la fonction de validation et le message d'erreur.
            // Si la règle est une simple fonction, $validationFn prend cette fonction.
            // Si la règle est un tableau, on utilise les clés 'validator' et 'message'.
            $validationFn = $rule['validator'] ?? $rule;
            $errorMessage = $rule['message'] ?? "Le champ $field n'est pas valide";

            // Appliquer la fonction de validation à la valeur du champ.
            // Si la validation retourne false, une erreur est ajoutée au tableau $errors.
            if (!$validationFn($value)) {
                $errors[$field] = $errorMessage;
            }
        }
        return $errors;
    }

    public static function withMessage(callable $validator, string $message): array
    {
        return [
            'validator' => $validator,
            'message' => $message
        ];
    }

    public static function requiredStringMax($maxLength = PHP_INT_MAX): callable
    {
        return function ($val) use ($maxLength) {
            return !empty($val) && is_string($val) && mb_strlen($val, 'UTF-8') <= $maxLength;
        };
    }

    public static function email(): callable
    {
        return function ($val) {
            return !empty($val) && filter_var($val, FILTER_VALIDATE_EMAIL) && strlen($val) <= 100;
        };
    }

    public static function password(): callable
    {
        return function ($val) {
            return !empty($val) && is_string($val) && mb_strlen($val, 'UTF-8') >= 8 &&
                preg_match('/[A-Z]/', $val) && // Au moins une lettre majuscule
                preg_match('/[a-z]/', $val) && // Au moins une lettre minuscule
                preg_match('/[0-9]/', $val);   // Au moins un chiffre
        };
    }
}
