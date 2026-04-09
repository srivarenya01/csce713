import angr
import claripy
import sys
import logging

logging.getLogger('angr').setLevel('ERROR')

def solve():
    binary_path = './crack_me'
    project = angr.Project(binary_path, auto_load_libs=False)

    start_addr = 0x401552
    
    input_len = 11
    symbolic_chars = [claripy.BVS(f'char_{i}', 8) for i in range(input_len)]
    stdin_content = claripy.Concat(*symbolic_chars + [claripy.BVV(b'\n')])

    state = project.factory.blank_state(
        addr=start_addr,
        add_options={angr.options.SYMBOLIC_WRITE_ADDRESSES,
                     angr.options.ZERO_FILL_UNCONSTRAINED_MEMORY}
    )
    
    state.posix.stdin.content = [stdin_content]
    state.posix.stdin.length = input_len + 1

    for c in symbolic_chars:
        state.solver.add(c >= 0x20)
        state.solver.add(c <= 0x7e)

    success_addr = 0x401662 
    failure_addr = 0x401709 
    
    simgr = project.factory.simulation_manager(state)
    simgr.explore(find=success_addr, avoid=failure_addr)

    if simgr.found:
        found_state = simgr.found[0]
        password = found_state.solver.eval(stdin_content, cast_to=bytes)
        return password.decode().strip()
    return None

if __name__ == "__main__":
    password = solve()
    if password:
        print(f"Found password: {password}")
    else:
        print("Failed to find password.")
