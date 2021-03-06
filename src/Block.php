<?php

namespace Log1x\AcfComposer;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use StoutLogic\AcfBuilder\FieldsBuilder;

abstract class Block extends Composer
{

    /**
     * block ID.
     *
     * @var string
     */
    public $id;

    /**
     * Current position inside a loop of blocks.
     *
     * @var int
     */
    public static $position = 0;

    /**
     * The block properties.
     *
     * @var array
     */
    public $block;

    /**
     * The block content.
     *
     * @var string
     */
    public $content;

    /**
     * The block preview status.
     *
     * @var bool
     */
    public $preview;

    /**
     * The current post ID.
     *
     * @param int
     */
    public $post;

    /**
     * The block classes.
     *
     * @param string
     */
    public $classes;

    /**
     * The block prefix.
     *
     * @var string
     */
    public $prefix = 'acf/';

    /**
     * The block namespace.
     *
     * @var string
     */
    public $namespace;

    /**
     * The display name of the block.
     *
     * @var string
     */
    public $name = '';

    /**
     * The slug of the block.
     *
     * @var string
     */
    public $slug = '';

    /**
     * The description of the block.
     *
     * @var string
     */
    public $description = '';

    /**
     * The category this block belongs to.
     *
     * @var string
     */
    public $category = '';

    /**
     * The icon of this block.
     *
     * @var string|array
     */
    public $icon = '';

    /**
     * An array of keywords the block will be found under.
     *
     * @var array
     */
    public $keywords = [];

    /**
     * An array of post types the block will be available to.
     *
     * @var array
     */
    public $post_types = ['post', 'page'];

    /**
     * The default display mode of the block that is shown to the user.
     *
     * @var string
     */
    public $mode = 'preview';

    /**
     * The block alignment class.
     *
     * @var string
     */
    public $align = '';

    /**
     * Features supported by the block.
     *
     * @var array
     */
    public $supports = [];

    /**
     * Compose and register the defined field groups with ACF.
     *
     * @param  callback $callback
     * @return void
     */
    public function compose($callback = null)
    {
        if (empty($this->name)) {
            return;
        }

        if ( empty($this->slug) ) {
            $this->slug = $this->slug();
        }

        if (empty($this->namespace)) {
            $this->namespace = Str::start($this->slug, $this->prefix);
        }

        acf_register_block([
            'name' => $this->slug,
            'title' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'icon' => $this->icon,
            'keywords' => $this->keywords,
            'post_types' => $this->post_types,
            'mode' => $this->mode,
            'align' => $this->align,
            'supports' => $this->supports,
            'enqueue_assets' => function () {
                return $this->enqueue();
            },
            'render_callback' => function ($block, $content = '', $preview = false, $post = 0) {
                echo $this->render($block, $content, $preview, $post);
            }
        ]);

        parent::compose(function () {
            if (! Arr::has($this->fields, 'location.0.0')) {
                Arr::set($this->fields, 'location.0.0', [
                    'param' => 'block',
                    'operator' => '==',
                    'value' => $this->namespace,
                ]);
            }
        });

        if( $globalfields = $this->app->config->get('acf.globalfields') ) {
            $this->appendGlobals($globalfields);
        }
    }

    /**
     * Render the ACF block.
     *
     * @param  array $block
     * @param  string $content
     * @param  bool $preview
     * @param  int $post
     * @return void
     */
    public function render($block, $content = '', $preview = false, $post = 0)
    {
        $this->set_id();

        $this->block = (object) $block;
        $this->content = $content;
        $this->preview = $preview;
        $this->post = $post;
        $this->classes = collect([
            'slug' => Str::start(Str::slug($this->block->title), 'b-'),
            'align' => ! empty($this->block->align) ? Str::start($this->block->align, 'align') : false,
            'classes' => $this->block->className ?? false,
        ])->filter()->implode(' ');

        return $this->view(
            Str::finish('views.blocks.', $this->slug),
            ['block' => $this]
        );
    }

    /**
     * Assets enqueued when rendering the block.
     *
     * @return void
     */
    public function enqueue()
    {
        //
    }

    /**
     * Get block slug based on the Class name.
     *
     * @return string
     */
    public function slug()
    {
        return str_replace('app-blocks-', '', $this->from_camel_case ( get_class( $this ) ) );
    }

    public function from_camel_case($input) {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }
        return implode('-', $ret);
    }

    /**
     * Set Block ID
     * Return an ID if set or the block position
     *
     * @return void
     */
    public function set_id()
    {
        $this->id = 'block-' . self::$position++;
    }

    /**
     * Append Global Fields
     *
     *  @return void
     */
    public function appendGlobals($globalfields) {
        foreach( $globalfields as $key => $global ) {
            // Replace keys (This has to be improved)
            $block_key = str_replace('group_', '', $this->fields['key']);
            $global_key = str_replace('group_', '', $global['key']);

            array_walk_recursive($global['fields'], function (&$val) use ($global_key, $block_key) {
                $val = str_replace($global_key, $block_key, $val);
            });

            // Find the position in the array where the design tab is located
            $design_tab_pos = array_search($key, array_column($this->fields['fields'], 'name'));

            // If there isn't a design tab, merge the global settings, else append to the beginning of the tab
            if( !$design_tab_pos ) {
                $this->fields['fields'] = array_merge( $this->fields['fields'], $global['fields']);
            } else {
                $global_array = array_filter($global['fields'], function ($var) use ($key) {
                    return ($var['name'] !== $key);
                });
                array_splice( $this->fields['fields'], $design_tab_pos + 1, 0, $global_array );
            }
        }
    }
}
