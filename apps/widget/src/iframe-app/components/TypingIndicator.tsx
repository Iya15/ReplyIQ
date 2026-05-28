export default function TypingIndicator() {
  return (
    <div className="flex items-end gap-2 riq-msg-enter">
      <div
        className="flex items-center gap-1.5 rounded-[var(--riq-radius)] rounded-bl-[3px]
                   bg-[var(--riq-surface)] px-3.5 py-2.5"
      >
        <span className="riq-dot" />
        <span className="riq-dot" />
        <span className="riq-dot" />
      </div>
    </div>
  );
}
