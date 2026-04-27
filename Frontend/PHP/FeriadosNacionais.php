<?php
// PHP/FeriadosNacionais.php
class FeriadosNacionais {
    
    /**
     * Calcula data da Páscoa para um ano específico
     * Fórmula de Gauss
     */
    public static function calcularPascoal($ano) {
        $a = $ano % 19;
        $b = floor($ano / 100);
        $c = $ano % 100;
        $d = floor($b / 4);
        $e = $b % 4;
        $f = floor(($b + 8) / 25);
        $g = floor(($b - $f + 1) / 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = floor($c / 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = floor(($a + 11 * $h + 22 * $l) / 451);
        $mes = floor(($h + $l - 7 * $m + 114) / 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;
        
        return sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
    }
    
    /**
     * Retorna todos feriados de um ano específico
     */
    public static function getFeriadosAno($ano) {
        $feriados = [];
        
        // Feriados com data fixa
        $fixos = [
            ['data' => "$ano-01-01", 'nome' => 'Confraternização Universal'],
            ['data' => "$ano-04-21", 'nome' => 'Tiradentes'],
            ['data' => "$ano-05-01", 'nome' => 'Dia do Trabalho'],
            ['data' => "$ano-07-16", 'nome' => 'Aniversário de Imperatriz'],
            ['data' => "$ano-09-07", 'nome' => 'Independência do Brasil'],
            ['data' => "$ano-10-12", 'nome' => 'Nossa Senhora Aparecida'],
            ['data' => "$ano-11-02", 'nome' => 'Finados'],
            ['data' => "$ano-11-15", 'nome' => 'Proclamação da República'],
            ['data' => "$ano-12-25", 'nome' => 'Natal'],
        ];
        
        $feriados = array_merge($feriados, $fixos);
        
        // Feriados móveis (baseados na Páscoa)
        $pascoa = self::calcularPascoal($ano);
        $pascoaObj = new DateTime($pascoa);
        
        $moveis = [
            ['offset' => -48, 'nome' => 'Carnaval'],
            ['offset' => -47, 'nome' => 'Carnaval'],
            ['offset' => -2,  'nome' => 'Sexta-feira Santa'],
            ['offset' => 0,   'nome' => 'Páscoa'],
            ['offset' => 60,  'nome' => 'Corpus Christi'],
        ];
        
        foreach ($moveis as $movel) {
            $dataObj = clone $pascoaObj;
            $dataObj->modify($movel['offset'] . ' days');
            $feriados[] = [
                'data' => $dataObj->format('Y-m-d'),
                'nome' => $movel['nome']
            ];
        }
        
        // Ordenar por data
        usort($feriados, function($a, $b) {
            return strcmp($a['data'], $b['data']);
        });
        
        return $feriados;
    }
    
    /**
     * Retorna feriados entre duas datas
     */
    public static function getFeriadosPeriodo($dataInicio, $dataFim) {
        $inicio = new DateTime($dataInicio);
        $fim = new DateTime($dataFim);
        
        $anoInicio = (int)$inicio->format('Y');
        $anoFim = (int)$fim->format('Y');
        
        $feriadosPeriodo = [];
        
        for ($ano = $anoInicio; $ano <= $anoFim; $ano++) {
            $feriadosAno = self::getFeriadosAno($ano);
            
            foreach ($feriadosAno as $feriado) {
                $dataFeriado = new DateTime($feriado['data']);
                
                if ($dataFeriado >= $inicio && $dataFeriado <= $fim) {
                    $feriadosPeriodo[] = $feriado;
                }
            }
        }
        
        return $feriadosPeriodo;
    }
}
?>