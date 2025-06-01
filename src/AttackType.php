<?php
enum AttackType: string
{
    case EasyNumbers = 'easy_numbers';
    case MediumDictionary = 'medium_dict';
    case MediumAlphaNum = 'medium_alpha_num';
    case HardMixed = 'hard_mixed';

    /**
     * Возвращает ожидаемое количество паролей для данного типа атаки.
     * @return int
     */
    public function expectedCount(): int
    {
        return match ($this) {
            self::EasyNumbers, self::MediumAlphaNum => 4,
            self::MediumDictionary => 12,
            self::HardMixed => 2,
        };
    }
}