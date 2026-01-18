<?php

namespace Eril\TblClass\Traits;

trait TableAliasGenerator
{
    private array $tableAliases = [];

    /**
     * Obtém um alias inteligente para a tabela
     * - 1 letra para tabelas de uma palavra (se única)
     * - 2 letras para tabelas de duas palavras
     * - Até 3 letras se necessário para evitar conflitos
     */
    public function getTableAlias(string $table): string
    {
        // Se já temos um alias para esta tabela, retorna ele
        if (isset($this->tableAliases[$table])) {
            return $this->tableAliases[$table];
        }

        // Gera um novo alias seguindo as regras
        $alias = $this->generateSmartAlias($table);

        // Armazena para reutilização
        $this->tableAliases[$table] = $alias;

        return $alias;
    }

    /**
     * Gera um alias inteligente baseado no nome da tabela
     */
    private function generateSmartAlias(string $table): string
    {
        $normalized = strtolower($table);
        $parts = explode('_', $normalized);
        $wordCount = count($parts);

        // Remove palavras vazias
        $parts = array_filter($parts, fn($part) => !empty($part));
        $wordCount = count($parts);

        // Para 1 palavra: tenta 1 letra, depois 2, depois 3
        if ($wordCount === 1) {
            return $this->getBestAliasForSingleWord($parts[0]);
        }

        // Para 2 palavras: começa com 2 letras
        if ($wordCount === 2) {
            return $this->getBestAliasForTwoWords($parts[0], $parts[1]);
        }

        // Para 3+ palavras: começa com 3 letras
        return $this->getBestAliasForMultipleWords($parts);
    }

    /**
     * Gera melhor alias para palavras únicas
     */
    /**
     * Gera melhor alias para palavras únicas - PRIMEIRA LETRA FIXA
     */
    /**
     * Gera melhor alias para palavras únicas - CONSOANTES PRIMEIRO, DEPOIS VOGAIS
     */
    private function getBestAliasForSingleWord(string $word): string
    {
        $letters = preg_replace('/[^a-z]/', '', $word);
        if (empty($letters)) return 'tbl';

        $firstLetter = $letters[0];

        // PASSO 1: Só a primeira letra
        if (!$this->isAliasUsed($firstLetter)) {
            return $firstLetter;
        }

        // PASSO 2: Extrai consoantes e vogais da palavra
        $consonants = $this->extractConsonants($letters);
        $vowels = $this->extractVowels($letters);

        // PASSO 3: Primeira letra + consoantes da palavra (exceto primeira se for consoante)
        foreach ($consonants as $consonant) {
            if ($consonant === $firstLetter) continue; // Pula se for a mesma
            $alias = $firstLetter . $consonant;
            if (!$this->isAliasUsed($alias)) {
                return $alias;
            }
        }

        // PASSO 4: Primeira letra + vogais da palavra
        foreach ($vowels as $vowel) {
            $alias = $firstLetter . $vowel;
            if (!$this->isAliasUsed($alias)) {
                return $alias;
            }
        }

        // PASSO 5: Duas consoantes da palavra
        if (count($consonants) >= 2) {
            for ($i = 0; $i < count($consonants); $i++) {
                for ($j = $i + 1; $j < count($consonants); $j++) {
                    $alias = $consonants[$i] . $consonants[$j];
                    if (!$this->isAliasUsed($alias)) {
                        return $alias;
                    }
                }
            }
        }

        // PASSO 6: Consoante + vogal
        foreach ($consonants as $consonant) {
            foreach ($vowels as $vowel) {
                $alias = $consonant . $vowel;
                if (!$this->isAliasUsed($alias)) {
                    return $alias;
                }
            }
        }

        // PASSO 7: Primeira + segunda consoante + vogal (3 letras)
        if (count($consonants) >= 2 && !empty($vowels)) {
            $alias = $consonants[0] . $consonants[1] . $vowels[0];
            if (!$this->isAliasUsed($alias)) {
                return $alias;
            }
        }

        // PASSO 8: Fallback com números
        for ($i = 1; $i <= 99; $i++) {
            $alias = $firstLetter . $i;
            if (!$this->isAliasUsed($alias)) {
                return $alias;
            }
        }

        return $firstLetter . 'x';
    }


    /**
     * Gera melhor alias para duas palavras
     */
    private function getBestAliasForTwoWords(string $word1, string $word2): string
    {
        // Primeira opção: primeira letra de cada palavra
        $alias = substr($word1, 0, 1) . substr($word2, 0, 1);
        if (!$this->isAliasUsed($alias)) {
            return $alias;
        }

        // Segunda opção: duas primeiras letras da primeira palavra
        if (strlen($word1) >= 2) {
            $alias = substr($word1, 0, 2);
            if (!$this->isAliasUsed($alias)) {
                return $alias;
            }
        }

        // Terceira opção: letras diferentes
        return $this->generateUniqueAlias($word1 . $word2, 2);
    }

    /**
     * Gera melhor alias para múltiplas palavras
     */
    private function getBestAliasForMultipleWords(array $parts): string
    {
        // Primeira letra de cada palavra (até 3)
        $alias = '';
        foreach ($parts as $part) {
            if (strlen($alias) < 3) {
                $alias .= substr($part, 0, 1);
            }
        }

        if (strlen($alias) >= 2 && !$this->isAliasUsed($alias)) {
            return $alias;
        }

        // Se não deu certo, tenta combinações
        $combined = implode('', $parts);
        return $this->generateUniqueAlias($combined, 3);
    }

    /**
     * Gera um alias único com fallbacks
     */
    private function generateUniqueAlias(string $word, int $maxLength = 3): string
    {
        $letters = preg_replace('/[^a-z]/', '', $word);

        if (strlen($letters) < 2) {
            return $this->generateFallbackAlias($letters);
        }

        // Tenta todas as combinações possíveis
        $combinations = $this->generateLetterCombinations($letters, min(3, $maxLength));

        foreach ($combinations as $combination) {
            if (!$this->isAliasUsed($combination)) {
                return $combination;
            }
        }

        // Se não achou combinação única, usa fallback
        return $this->generateFallbackAlias($letters);
    }

    /**
     * Gera combinações de letras
     */
    private function generateLetterCombinations(string $letters, int $length): array
    {
        $combinations = [];

        // Combinações de 1-3 letras
        for ($i = 1; $i <= $length; $i++) {
            if ($i === 1) {
                // Letras únicas
                for ($j = 0; $j < strlen($letters); $j++) {
                    $combinations[] = $letters[$j];
                }
            } elseif ($i === 2) {
                // Pares
                for ($j = 0; $j < strlen($letters); $j++) {
                    for ($k = 0; $k < strlen($letters); $k++) {
                        if ($j !== $k) {
                            $combinations[] = $letters[$j] . $letters[$k];
                        }
                    }
                }
            } else {
                // Trios (apenas primeiras combinações para performance)
                $max = min(3, strlen($letters));
                for ($j = 0; $j < $max; $j++) {
                    for ($k = 0; $k < $max; $k++) {
                        for ($l = 0; $l < $max; $l++) {
                            if ($j !== $k && $j !== $l && $k !== $l) {
                                $combinations[] = $letters[$j] . $letters[$k] . $letters[$l];
                            }
                        }
                    }
                }
            }
        }

        // Remove duplicados e retorna
        return array_unique($combinations);
    }

    /**
     * Fallback para quando não consegue combinação única
     */
    private function generateFallbackAlias(string $letters): string
    {
        $base = substr($letters, 0, 1);
        if (empty($base)) {
            $base = 't'; // fallback geral
        }

        $counter = 1;
        do {
            $alias = $base . $counter;
            $counter++;
        } while ($this->isAliasUsed($alias) && $counter < 100);

        return $alias;
    }

    /**
     * Extrai consoantes de uma string
     */
    private function extractConsonants(string $word): array
    {
        $consonants = preg_replace('/[aeiou]/', '', $word);
        return $consonants ? array_unique(str_split($consonants)) : [];
    }

    /**
     * Extrai vogais de uma string  
     */
    private function extractVowels(string $word): array
    {
        $vowels = preg_replace('/[^aeiou]/', '', $word);
        return $vowels ? array_unique(str_split($vowels)) : [];
    }

    /**
     * Verifica se um alias já está em uso
     */
    private function isAliasUsed(string $alias): bool
    {
        return in_array($alias, $this->tableAliases, true);
    }

    /**
     * Reseta todos os aliases (útil para testes)
     */
    public function resetAliases(): void
    {
        $this->tableAliases = [];
    }

    /**
     * Obtém todos os aliases mapeados (para debug)
     */
    public function getAliasesMap(): array
    {
        return $this->tableAliases;
    }

    /**
     * Obtém alias sem armazenar (apenas para consulta)
     */
    public function peekTableAlias(string $table): string
    {
        return $this->generateSmartAlias($table);
    }
}
