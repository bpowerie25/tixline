<?php

return [
    'encoding' => 'UTF-8',
    'finalize' => true,
    'ignoreNonStrings' => false,
    'cachePath' => storage_path('app/purifier'),
    'cacheFileMode' => 0755,
    'settings' => [
        'default' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'div,b,strong,i,em,u,a[href|title],ul,ol,li,p[style],br,span[style],img[width|height|alt|src]',
            'CSS.AllowedProperties' => 'font,font-size,font-weight,font-style,font-family,text-decoration,padding-left,color,background-color,text-align',
            'AutoFormat.AutoParagraph' => true,
            'AutoFormat.RemoveEmpty' => true,
        ],

        /*
        |----------------------------------------------------------------------
        | Email body sanitization (defence-in-depth layer)
        |----------------------------------------------------------------------
        | Conservative allowlist for rendering inbound email and comment HTML.
        | No script, no style, no iframe, no object/embed, no form elements,
        | no event handlers, no javascript:/data: URIs.
        | Images are allowed but rendered via a proxy/sandbox with remote
        | loading disabled by default.
        */
        'email_body' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'p,br,div,span[style],strong,b,em,i,u,s,strike,del,'
                .'a[href|title|rel],ul,ol,li,blockquote,pre,code,h1,h2,h3,h4,h5,h6,'
                .'table,thead,tbody,tfoot,tr,th[colspan|rowspan],td[colspan|rowspan],'
                .'img[src|alt|width|height],hr,sub,sup,dl,dt,dd',
            'HTML.Nofollow' => true,
            'HTML.TargetBlank' => true,
            'CSS.AllowedProperties' => 'color,background-color,font-weight,font-style,'
                .'text-decoration,text-align,padding,padding-left,padding-right,'
                .'margin,margin-left,margin-right,border,border-collapse,'
                .'width,max-width,height',
            'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true, 'cid' => true],
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => false,
            'Attr.AllowedFrameTargets' => ['_blank'],
        ],
    ],
];
