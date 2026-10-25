@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'block w-full border-gray-300 rounded-lg shadow-sm focus:border-brand-500 focus:ring-brand-500 placeholder:text-gray-400']) }}>