<?php
/**
 * Custom Joomla Form Field: IONOS Models dropdown
 */

defined('_JEXEC') or die;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;

class JFormFieldIonosmodels extends FormField
{
    protected $type = 'ionosmodels';

    protected function getInput()
    {
        // Read token and endpoint from current form values
        $token    = (string) $this->form->getValue('ionos_token', 'params');
        $endpoint = (string) $this->form->getValue('ionos_endpoint', 'params');
        if ($endpoint === '') {
            $endpoint = 'https://openai.inference.de-txl.ionos.com/v1/chat/completions';
        }

        // Derive models endpoint from chat/completions endpoint
        $modelsUrl = $endpoint;
        if (strpos($modelsUrl, '/chat/completions') !== false) {
            $modelsUrl = str_replace('/chat/completions', '/models', $modelsUrl);
        } else {
            // Fallback: replace after /v1/
            $modelsUrl = preg_replace('#/v1/.*$#', '/v1/models', $modelsUrl);
            if (!$modelsUrl) {
                $modelsUrl = 'https://openai.inference.de-txl.ionos.com/v1/models';
            }
        }

        $options = [];
        $error = '';

        // Try to load from API when token is present
        if ($token !== '') {
            try {
                $http = HttpFactory::getHttp();
                $headers = [
                    'Authorization' => 'Bearer ' . trim($token),
                    'Accept'        => 'application/json',
                ];
                $resp = $http->get($modelsUrl, $headers);
                if ($resp->code >= 200 && $resp->code < 300) {
                    $data = json_decode($resp->body, true);
                    if (isset($data['data']) && is_array($data['data'])) {
                        foreach ($data['data'] as $model) {
                            if (!isset($model['id'])) continue;
                            $id = (string) $model['id'];
                            $name = isset($model['name']) ? (string) $model['name'] : $id;
                            $options[$id] = $name . ' (' . $id . ')';
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore, will fallback to static list
            }
        }

        // Fallback to provided static model list if API not available or token missing
        if (empty($options)) {
            $fallbackJson = '{"data":[{"id":"BAAI/bge-large-en-v1.5"},{"id":"openai/gpt-oss-120b"},{"id":"BAAI/bge-m3"},{"id":"sentence-transformers/paraphrase-multilingual-mpnet-base-v2"},{"id":"meta-llama/Meta-Llama-3.1-405B-Instruct-FP8"},{"id":"meta-llama/Llama-3.3-70B-Instruct"},{"id":"mistralai/Mistral-Small-24B-Instruct"},{"id":"openGPT-X/Teuken-7B-instruct-commercial"},{"id":"mistralai/Mixtral-8x7B-Instruct-v0.1"},{"id":"black-forest-labs/FLUX.1-schnell"},{"id":"meta-llama/Meta-Llama-3.1-8B-Instruct"},{"id":"stabilityai/stable-diffusion-xl-base-1.0"},{"id":"meta-llama/CodeLlama-13b-Instruct-hf"},{"id":"mistralai/Mistral-Nemo-Instruct-2407"}],"object":"list"}';
            $data = json_decode($fallbackJson, true);
            if (isset($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $model) {
                    if (!isset($model['id'])) continue;
                    $id = (string) $model['id'];
                    $options[$id] = $id;
                }
            }
        }

        // Build select element
        $attrs = [];
        $attrs[] = 'name="' . $this->name . '"';
        $attrs[] = 'class="inputbox"';
        $html = '<select ' . implode(' ', $attrs) . '>';

        if (empty($options)) {
            $html .= '<option value="">' . htmlspecialchars($error ?: 'No models available', ENT_QUOTES, 'UTF-8') . '</option>';
        } else {
            $html .= '<option value="">' . htmlspecialchars(Text::_('MOD_BEARS_AICHATBOT_MODEL_SELECT'), ENT_QUOTES, 'UTF-8') . '</option>';
            $current = (string) $this->value;
            foreach ($options as $val => $label) {
                $sel = ($current === $val) ? ' selected' : '';
                $html .= '<option value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
                      . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
            }
        }

        $html .= '</select>';
        return $html;
    }
}
