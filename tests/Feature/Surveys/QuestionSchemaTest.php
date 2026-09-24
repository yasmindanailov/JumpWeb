<?php

namespace Tests\Feature\Surveys;

use App\Domain\Platform\Services\Surveys\QuestionSchema;
use Tests\TestCase;

/**
 * **Las preguntas de una encuesta, normalizadas** (`docs/specs/encuestas.md` §4.1, T1): lo que el panel guarda es lo
 * que la puerta, el correo y el cuadro leen, y la regla de qué vale y qué se descarta vive en un solo sitio.
 */
class QuestionSchemaTest extends TestCase
{
    public function test_it_keeps_valid_questions_in_order_and_drops_the_broken_ones(): void
    {
        $questions = QuestionSchema::normalize([
            ['key' => 'ambiente', 'type' => 'scale', 'required' => 1, 'label' => ['es' => '¿Qué tal el ambiente?', 'en' => 'How was it?']],
            ['key' => 'volverias', 'type' => 'yesno', 'label' => '¿Volverías?'],
            ['key' => 'comentario', 'type' => 'text', 'label' => ['es' => 'Cuéntanos', 'fr' => '']],
            ['key' => 'que-mejorar', 'type' => 'multi', 'label' => ['es' => '¿Qué mejorar?'], 'options' => [['key' => 'bar', 'label' => ['es' => 'El bar']], ['key' => 'colas', 'label' => 'Las colas'], ['key' => 'bar', 'label' => 'Repetida']]],
            ['key' => 'Mal Clave', 'type' => 'choice', 'label' => 'x', 'options' => [['key' => 'a', 'label' => 'A'], ['key' => 'b', 'label' => 'B']]],
            ['key' => 'ambiente', 'type' => 'text', 'label' => 'repetida'],
            ['key' => 'sinopciones', 'type' => 'choice', 'label' => 'x'],
            ['key' => 'raro', 'type' => 'fecha', 'label' => 'x', 'options' => [['key' => 'a', 'label' => 'A'], ['key' => 'b', 'label' => 'B']]],
            'no es una pregunta',
        ]);

        $this->assertSame(['ambiente', 'volverias', 'comentario', 'que-mejorar', 'raro'], array_column($questions, 'key'));
        $this->assertSame('scale', $questions[0]['type']);
        $this->assertTrue($questions[0]['required']);
        $this->assertSame(['es' => '¿Qué tal el ambiente?', 'en' => 'How was it?'], $questions[0]['label']);
        $this->assertSame(['es' => '¿Volverías?'], $questions[1]['label'], 'un rótulo en texto plano es el español');
        $this->assertSame(['es' => 'Cuéntanos'], $questions[2]['label'], 'un idioma vacío no se guarda');
        $this->assertSame(['bar', 'colas'], array_column($questions[3]['options'], 'key'), 'una opción repetida se descarta');
        $this->assertSame('choice', $questions[4]['type'], 'un tipo desconocido cae a elección única');
        $this->assertSame([], $questions[0]['options'], 'una escala no lleva opciones');
    }

    public function test_it_says_which_values_a_question_accepts(): void
    {
        $choice = ['type' => 'choice', 'options' => [['key' => 'si'], ['key' => 'no']]];
        $multi = ['type' => 'multi', 'options' => [['key' => 'bar'], ['key' => 'colas']]];

        $this->assertTrue(QuestionSchema::accepts($choice, 'si'));
        $this->assertFalse(QuestionSchema::accepts($choice, 'quizas'));
        $this->assertTrue(QuestionSchema::accepts($multi, ['bar', 'colas']));
        $this->assertFalse(QuestionSchema::accepts($multi, ['bar', 'otra']));
        $this->assertFalse(QuestionSchema::accepts($multi, []));
        $this->assertTrue(QuestionSchema::accepts(['type' => 'scale'], 5));
        $this->assertFalse(QuestionSchema::accepts(['type' => 'scale'], 6));
        $this->assertFalse(QuestionSchema::accepts(['type' => 'scale'], '5'));
        $this->assertTrue(QuestionSchema::accepts(['type' => 'yesno'], false));
        $this->assertTrue(QuestionSchema::accepts(['type' => 'text'], 'Muy bien todo'));
        $this->assertFalse(QuestionSchema::accepts(['type' => 'text'], str_repeat('a', QuestionSchema::TEXT_MAX + 1)));
        $this->assertFalse(QuestionSchema::accepts(['type' => 'text'], '   '));
    }

    public function test_the_label_falls_back_to_spanish_and_to_the_key(): void
    {
        $this->assertSame('Hola', QuestionSchema::label(['es' => 'Hola'], 'fr'));
        $this->assertSame('Bonjour', QuestionSchema::label(['es' => 'Hola', 'fr' => 'Bonjour'], 'fr'));
        $this->assertSame(['es' => 'sin-rotulo'], QuestionSchema::normalize([['key' => 'sin-rotulo', 'type' => 'text']])[0]['label']);
    }
}
