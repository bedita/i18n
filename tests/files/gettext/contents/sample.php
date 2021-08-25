<?php

    __('This is a php sample', true);
    __('A php content', true);
    __('A php string with "double quotes"', true);
    __('A php string with \'single quotes\'', true);

    // __
    __('1 test __')
    __('2 test __', true)
    __('3 test __', true)
    __('4 test __', true)

    // __n
    __n('1 test __n', '1 test __n plural', 1, null)

    // __d
    __d('DomainSampleD', '1 test __d', null)

    // __dn
    __dn('DomainSampleDN', '1 test __dn', '1 test __dn plural', 1, null)

    // __x
    __x('ContextSampleX', '1 test __x', null)

    // __xn
    __xn('ContextSampleXN', '1 test __xn', '1 test __xn plural', 1, null)

    // __dx
    __dx('DomainSampleDX', 'ContextSampleDX', '1 test __dx', null)

    // __dxn
    __dxn('DomainSampleDXN', 'ContextSampleDXN', '1 test __dxn', '1 test __dxn plural', 1, null)
